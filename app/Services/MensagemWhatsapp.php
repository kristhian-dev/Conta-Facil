<?php

declare(strict_types=1);

namespace App\Services;

defined('APP_ROOT') || exit;

/**
 * Textos sugeridos para contato com o cliente pelo WhatsApp (link wa.me).
 *
 * O ContaFácil NÃO envia mensagens: os textos apenas pré-preenchem a conversa,
 * e o contador revisa, edita e envia pelo próprio WhatsApp. Nada é gravado.
 *
 * As pendências vêm do RadarService (mesmas regras do Radar Contábil):
 *   - documento_recebimento -> documentos solicitados ou rejeitados (aguardam o cliente);
 *   - obrigacao_atrasada    -> obrigações em aberto com vencimento já passado;
 *   - obrigacao_proxima     -> obrigações que vencem hoje ou nos próximos dias.
 * Documentos recebidos aguardando análise (documento_analise) dependem do escritório
 * e nunca são cobrados. Obrigações concluídas/canceladas não chegam do Radar.
 *
 * Para não expor dados, os textos usam apenas: nome do cliente, título do documento
 * ou tipo/competência da obrigação e datas. Nunca CPF/CNPJ, valores, observações,
 * motivo de rejeição ou links do sistema.
 */
final class MensagemWhatsapp
{
    /** Máximo de itens listados por mensagem (o restante vira "e mais N"). */
    private const MAX_ITENS = 5;

    /**
     * Modelos disponíveis para o cliente, na ordem de exibição.
     * O modelo "geral" sempre existe; os demais só quando há pendências do tipo.
     *
     * @param array $cliente    registro do cliente (razao_social, nome_fantasia)
     * @param array $pendencias pendências do cliente vindas de RadarService::analisar()
     * @param string $assinatura nome do usuário que fará o contato
     * @return list<array{chave: string, rotulo: string, texto: string}>
     */
    public static function modelos(array $cliente, array $pendencias, string $assinatura): array
    {
        $nome = self::nome($cliente);
        $porTipo = ['documento_recebimento' => [], 'obrigacao_atrasada' => [], 'obrigacao_proxima' => []];
        foreach ($pendencias as $p) {
            if (isset($porTipo[$p['tipo'] ?? ''])) {
                $porTipo[$p['tipo']][] = $p;
            }
        }

        $modelos = [['chave' => 'geral', 'rotulo' => 'Contato geral', 'texto' => self::geral($nome, $assinatura)]];

        if ($porTipo['documento_recebimento'] !== []) {
            $modelos[] = ['chave' => 'documentos', 'rotulo' => 'Documentos pendentes (' . count($porTipo['documento_recebimento']) . ')',
                'texto' => self::documentos($nome, $porTipo['documento_recebimento'], $assinatura)];
        }
        if ($porTipo['obrigacao_atrasada'] !== []) {
            $modelos[] = ['chave' => 'atrasadas', 'rotulo' => 'Obrigações com vencimento passado (' . count($porTipo['obrigacao_atrasada']) . ')',
                'texto' => self::atrasadas($nome, $porTipo['obrigacao_atrasada'], $assinatura)];
        }
        if ($porTipo['obrigacao_proxima'] !== []) {
            $modelos[] = ['chave' => 'proximas', 'rotulo' => 'Próximos vencimentos (' . count($porTipo['obrigacao_proxima']) . ')',
                'texto' => self::proximas($nome, $porTipo['obrigacao_proxima'], $assinatura)];
        }

        return $modelos;
    }

    public static function geral(string $nome, string $assinatura): string
    {
        return "Olá, {$nome}! Tudo bem?" . self::assinatura($assinatura, 'Aqui é %s, do seu escritório de contabilidade.', ' ');
    }

    private static function documentos(string $nome, array $pendencias, string $assinatura): string
    {
        $itens = self::itens($pendencias, static function (array $p): string {
            $detalhe = match (true) {
                ($p['status'] ?? '') === 'rejeitado' => 'precisamos de um novo envio',
                ($p['motivo'] ?? '') === 'prazo_vencido' => 'prazo encerrado em ' . formatar_data($p['data']),
                $p['data'] !== null => 'prazo: ' . formatar_data($p['data']),
                default => '',
            };

            return $p['titulo'] . ($detalhe !== '' ? " ({$detalhe})" : '');
        }, 'documento', 'documentos');

        $plural = count($pendencias) > 1;

        return "Olá, {$nome}! Para darmos andamento aos seus serviços contábeis, precisamos "
            . ($plural ? 'dos seguintes documentos:' : 'do seguinte documento:') . "\n{$itens}\n\n"
            . 'Você pode ' . ($plural ? 'enviá-los' : 'enviá-lo') . ' por aqui mesmo. Obrigado!'
            . self::assinatura($assinatura);
    }

    private static function atrasadas(string $nome, array $pendencias, string $assinatura): string
    {
        // Tom neutro: "atrasada" no sistema significa que a obrigação ainda não foi
        // marcada como concluída pelo escritório; não prova falta de pagamento do cliente.
        $itens = self::itens($pendencias, static fn (array $p): string => $p['titulo'] . ' (vencimento: ' . formatar_data($p['data']) . ')',
            'obrigação', 'obrigações');
        $plural = count($pendencias) > 1;

        return "Olá, {$nome}! Em nosso controle, " . ($plural ? 'as obrigações abaixo constam' : 'a obrigação abaixo consta')
            . " com vencimento já passado:\n{$itens}\n\n"
            . 'Se ' . ($plural ? 'já estiverem resolvidas' : 'já estiver resolvida') . ', pode desconsiderar esta mensagem; '
            . 'se possível, nos envie o comprovante. Qualquer dúvida, estamos à disposição.'
            . self::assinatura($assinatura);
    }

    private static function proximas(string $nome, array $pendencias, string $assinatura): string
    {
        $itens = self::itens($pendencias, static fn (array $p): string => $p['titulo']
            . (($p['motivo'] ?? '') === 'vence_hoje' ? ' (vence hoje, ' : ' (vence em ') . formatar_data($p['data']) . ')',
            'obrigação', 'obrigações');

        return "Olá, {$nome}! Lembrete " . (count($pendencias) > 1 ? 'dos próximos vencimentos' : 'do próximo vencimento') . ":\n{$itens}\n\n"
            . 'Qualquer dúvida, estamos à disposição.' . self::assinatura($assinatura);
    }

    /** Lista com marcadores ("- item"), limitada a MAX_ITENS. */
    private static function itens(array $pendencias, callable $formatar, string $singular, string $plural): string
    {
        $linhas = array_map(static fn (array $p): string => '- ' . $formatar($p), array_slice($pendencias, 0, self::MAX_ITENS));
        $restante = count($pendencias) - self::MAX_ITENS;
        if ($restante > 0) {
            $linhas[] = '- e mais ' . plural($restante, $singular, $plural);
        }

        return implode("\n", $linhas);
    }

    /** Nome usado na saudação: nome fantasia, quando houver; senão, razão social. */
    private static function nome(array $cliente): string
    {
        $fantasia = trim((string) ($cliente['nome_fantasia'] ?? ''));

        return $fantasia !== '' ? $fantasia : trim((string) $cliente['razao_social']);
    }

    private static function assinatura(string $assinatura, string $formato = "\n%s", string $prefixo = ''): string
    {
        $assinatura = trim($assinatura);

        return $assinatura === '' ? '' : $prefixo . sprintf($formato, $assinatura);
    }
}
