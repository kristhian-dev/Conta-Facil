<?php

declare(strict_types=1);

namespace App\Services;

defined('APP_ROOT') || exit;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Obrigacao;

/**
 * Radar Contábil: identifica o que precisa da atenção do escritório e
 * classifica cada cliente por nível de risco.
 *
 * ORIGEM DOS DADOS (2 consultas, independentemente do número de clientes):
 *   - Obrigacao::listarParaRadar() -> obrigações em aberto que vencem até hoje + janela;
 *   - Documento::listarParaRadar() -> documentos solicitados, rejeitados ou recebidos.
 *   (+ 1 contagem de clientes ativos para o resumo "sem pendências")
 *
 * REGRA DE CLASSIFICAÇÃO (sem pesos nem pontuação):
 *   Cada registro gera no máximo UMA pendência, com um tipo e uma gravidade:
 *
 *   Pendência                                                    Tipo                    Gravidade
 *   -----------------------------------------------------------  ----------------------  ---------
 *   Obrigação em aberto com vencimento anterior a hoje            obrigacao_atrasada      alta
 *   Obrigação em aberto que vence hoje ou em até N dias           obrigacao_proxima       média
 *   Documento solicitado/rejeitado com prazo de envio vencido     documento_recebimento   alta
 *   Documento solicitado/rejeitado dentro do prazo ou sem prazo   documento_recebimento   baixa
 *   Documento recebido aguardando análise há mais de M dias       documento_analise       média
 *   Documento recebido aguardando análise há até M dias           documento_analise       baixa
 *
 *   N = config prazos.janela_alerta_dias; M = config prazos.analise_parada_dias.
 *   Obrigações concluídas/canceladas e documentos aprovados nunca entram.
 *
 *   Nível de risco do cliente = a MAIOR gravidade entre as suas pendências:
 *     alguma alta -> ALTO; senão alguma média -> MÉDIO; senão -> BAIXO.
 *   Clientes sem pendências não aparecem na lista.
 *
 *   Ordem da lista: nível de risco, depois quantidade de pendências altas,
 *   médias e total (desempates diretos, sem fórmula), e por fim o nome.
 *
 * Um documento vinculado a uma obrigação atrasada conta como pendência própria:
 * são dois registros distintos, cada um com sua ação (cobrar o documento e
 * entregar a obrigação). Nenhum registro é contado duas vezes.
 */
final class RadarService
{
    public const TIPOS = [
        'obrigacao_atrasada'    => 'Obrigações atrasadas',
        'obrigacao_proxima'     => 'Obrigações próximas do vencimento',
        'documento_recebimento' => 'Documentos aguardando recebimento',
        'documento_analise'     => 'Documentos aguardando análise',
    ];

    public const RISCOS = [
        'alto'  => 'Alto risco',
        'medio' => 'Médio risco',
        'baixo' => 'Baixo risco',
    ];

    private const RISCO_DA_GRAVIDADE = ['alta' => 'alto', 'media' => 'medio', 'baixa' => 'baixo'];
    private const ORDEM_GRAVIDADE    = ['alta' => 0, 'media' => 1, 'baixa' => 2];
    private const ORDEM_RISCO        = ['alto' => 0, 'medio' => 1, 'baixo' => 2];

    private Obrigacao $obrigacoes;
    private Documento $documentos;
    private Cliente $clientes;

    public function __construct(?Obrigacao $obrigacoes = null, ?Documento $documentos = null, ?Cliente $clientes = null)
    {
        $this->obrigacoes = $obrigacoes ?? new Obrigacao();
        $this->documentos = $documentos ?? new Documento();
        $this->clientes   = $clientes ?? new Cliente();
    }

    /**
     * Analisa obrigações e documentos.
     *
     * @param int|null $clienteId     limita a um cliente (filtro da tela ou, no futuro, Auth::clienteRestrito())
     * @param int|null $responsavelId limita aos clientes de um responsável
     * @return array{resumo: array, clientes: array, janela: int, dias_analise: int}
     */
    public function analisar(?int $clienteId = null, ?int $responsavelId = null): array
    {
        $pendencias = [];

        foreach ($this->obrigacoes->listarParaRadar($clienteId, $responsavelId) as $o) {
            $pendencias[] = self::pendenciaDeObrigacao($o);
        }
        foreach ($this->documentos->listarParaRadar($clienteId, $responsavelId) as $d) {
            $pendencias[] = self::pendenciaDeDocumento($d);
        }

        $clientes = self::agruparPorCliente($pendencias);

        return [
            'resumo'       => $this->resumo($pendencias, $clientes, $clienteId, $responsavelId),
            'clientes'     => $clientes,
            'janela'       => Obrigacao::janela(),
            'dias_analise' => Documento::diasAnaliseParada(),
        ];
    }

    /**
     * Filtros de exibição aplicados sobre o resultado:
     *   - $risco: mantém clientes com esse nível GERAL de risco;
     *   - $tipo:  mantém apenas as pendências desse tipo (o nível de risco
     *             exibido continua sendo o geral do cliente).
     */
    public static function filtrar(array $clientes, string $risco = '', string $tipo = ''): array
    {
        $resultado = [];

        foreach ($clientes as $cliente) {
            if ($risco !== '' && $cliente['risco'] !== $risco) {
                continue;
            }
            if ($tipo !== '') {
                $cliente['pendencias'] = array_values(array_filter(
                    $cliente['pendencias'],
                    static fn (array $p): bool => $p['tipo'] === $tipo
                ));
                if ($cliente['pendencias'] === []) {
                    continue;
                }
            }
            $resultado[] = $cliente;
        }

        return $resultado;
    }

    /**
     * Regra das obrigações (já filtradas: em aberto e vencendo até hoje + janela).
     */
    public static function pendenciaDeObrigacao(array $o): array
    {
        $dias = (int) $o['dias_para_vencer'];

        [$tipo, $gravidade, $motivo] = match (true) {
            $dias < 0   => ['obrigacao_atrasada', 'alta', 'atrasada'],
            $dias === 0 => ['obrigacao_proxima', 'media', 'vence_hoje'],
            default     => ['obrigacao_proxima', 'media', 'vence_em'],
        };

        return self::pendencia($o, 'obrigacao', $tipo, $gravidade, $motivo, abs($dias), [
            'titulo'      => $o['tipo_nome'] . ' ' . formatar_competencia($o['competencia']),
            'data'        => $o['data_vencimento'],
            'status'      => $o['status'],
        ]);
    }

    /**
     * Regra dos documentos (já filtrados: solicitado, rejeitado ou recebido).
     */
    public static function pendenciaDeDocumento(array $d): array
    {
        if ($d['status'] === 'recebido') {
            $dias  = (int) $d['dias_aguardando'];
            $parado = $dias > Documento::diasAnaliseParada();
            return self::pendencia($d, 'documento', 'documento_analise', $parado ? 'media' : 'baixa',
                $parado ? 'analise_parada' : 'analise_recente', $dias, [
                    'titulo' => $d['titulo'],
                    'data'   => substr((string) $d['recebido_em'], 0, 10),
                    'status' => $d['status'],
                ] + self::vinculo($d));
        }

        // solicitado ou rejeitado (aguardando envio/reenvio pelo cliente)
        $diasPrazo = $d['dias_prazo'] === null ? null : (int) $d['dias_prazo'];
        [$gravidade, $motivo] = match (true) {
            $diasPrazo === null => ['baixa', 'sem_prazo'],
            $diasPrazo < 0      => ['alta', 'prazo_vencido'],
            default             => ['baixa', 'no_prazo'],
        };

        return self::pendencia($d, 'documento', 'documento_recebimento', $gravidade, $motivo, abs((int) $diasPrazo), [
            'titulo' => $d['titulo'],
            'data'   => $d['prazo_envio'],
            'status' => $d['status'],
        ] + self::vinculo($d));
    }

    /**
     * Nível de risco = maior gravidade presente.
     *
     * @param string[] $gravidades
     */
    public static function riscoDoCliente(array $gravidades): string
    {
        foreach (['alta', 'media'] as $gravidade) {
            if (in_array($gravidade, $gravidades, true)) {
                return self::RISCO_DA_GRAVIDADE[$gravidade];
            }
        }

        return 'baixo';
    }

    // ------------------------------------------------------------------

    private static function pendencia(array $linha, string $origem, string $tipo, string $gravidade, string $motivo, int $dias, array $extra): array
    {
        return [
            'chave'     => $origem . ':' . $linha['id'],
            'origem'    => $origem,
            'id'        => (int) $linha['id'],
            'tipo'      => $tipo,
            'gravidade' => $gravidade,
            'motivo'    => $motivo,
            'dias'      => $dias,
            'cliente'   => [
                'id'               => (int) $linha['cliente_id'],
                'nome'             => $linha['cliente_nome'],
                'status'           => $linha['cliente_status'],
                'responsavel_id'   => (int) $linha['cliente_responsavel_id'],
                'responsavel_nome' => $linha['cliente_responsavel_nome'],
            ],
        ] + $extra;
    }

    private static function vinculo(array $d): array
    {
        return [
            'obrigacao_id'     => $d['obrigacao_id'] === null ? null : (int) $d['obrigacao_id'],
            'obrigacao_titulo' => $d['obrigacao_id'] === null ? null : $d['tipo_nome'] . ' ' . formatar_competencia($d['obrigacao_competencia']),
        ];
    }

    private static function agruparPorCliente(array $pendencias): array
    {
        $clientes = [];

        foreach ($pendencias as $p) {
            $id = $p['cliente']['id'];
            $clientes[$id] ??= $p['cliente'] + [
                'pendencias' => [],
                'por_tipo'   => array_fill_keys(array_keys(self::TIPOS), 0),
                'por_gravidade' => ['alta' => 0, 'media' => 0, 'baixa' => 0],
            ];

            unset($p['cliente']);
            $clientes[$id]['pendencias'][] = $p;
            $clientes[$id]['por_tipo'][$p['tipo']]++;
            $clientes[$id]['por_gravidade'][$p['gravidade']]++;
        }

        foreach ($clientes as &$cliente) {
            $cliente['risco'] = self::riscoDoCliente(array_column($cliente['pendencias'], 'gravidade'));
            $cliente['total'] = count($cliente['pendencias']);
            usort($cliente['pendencias'], static fn (array $a, array $b): int =>
                [self::ORDEM_GRAVIDADE[$a['gravidade']], (string) $a['data'], $a['chave']]
                <=> [self::ORDEM_GRAVIDADE[$b['gravidade']], (string) $b['data'], $b['chave']]);
        }
        unset($cliente);

        usort($clientes, static fn (array $a, array $b): int =>
            [self::ORDEM_RISCO[$a['risco']], -$a['por_gravidade']['alta'], -$a['por_gravidade']['media'], -$a['total'], $a['nome']]
            <=> [self::ORDEM_RISCO[$b['risco']], -$b['por_gravidade']['alta'], -$b['por_gravidade']['media'], -$b['total'], $b['nome']]);

        return $clientes;
    }

    private function resumo(array $pendencias, array $clientes, ?int $clienteId, ?int $responsavelId): array
    {
        $contar = static fn (callable $condicao): int => count(array_filter($pendencias, $condicao));

        $ativosNoEscopo = $this->clientes->contar(
            ['status' => 'ativo', 'responsavel_id' => $responsavelId ?? 0],
            $clienteId
        );
        $ativosComPendencia = count(array_filter($clientes, static fn (array $c): bool => $c['status'] === 'ativo'));

        return [
            'obrigacoes_atrasadas'      => $contar(static fn ($p) => $p['tipo'] === 'obrigacao_atrasada'),
            'obrigacoes_proximas'       => $contar(static fn ($p) => $p['tipo'] === 'obrigacao_proxima'),
            'vencem_hoje'               => $contar(static fn ($p) => $p['motivo'] === 'vence_hoje'),
            'documentos_recebimento'    => $contar(static fn ($p) => $p['tipo'] === 'documento_recebimento'),
            'documentos_prazo_vencido'  => $contar(static fn ($p) => $p['motivo'] === 'prazo_vencido'),
            'documentos_analise'        => $contar(static fn ($p) => $p['tipo'] === 'documento_analise'),
            'documentos_analise_parada' => $contar(static fn ($p) => $p['motivo'] === 'analise_parada'),
            'total_pendencias'          => count($pendencias),
            'clientes_por_risco'        => array_merge(
                array_fill_keys(array_keys(self::RISCOS), 0),
                array_count_values(array_column($clientes, 'risco'))
            ),
            'clientes_com_pendencias'   => count($clientes),
            'clientes_ativos_em_dia'    => max(0, $ativosNoEscopo - $ativosComPendencia),
        ];
    }
}
