<?php

declare(strict_types=1);

namespace App\Models;

defined('APP_ROOT') || exit;

use App\Core\Model;

/**
 * Acesso à tabela documentos.
 *
 * Fluxo de situação (somente os status do banco):
 *   solicitado -> recebido -> aprovado
 *                         \-> rejeitado -> recebido (novo arquivo) -> ...
 *
 * As mudanças de situação usam "WHERE status IN (...)" no próprio UPDATE:
 * se outra pessoa alterou o documento no meio do caminho, nada é gravado
 * e o método retorna false.
 *
 * Pendências exibidas (calculadas, não gravadas):
 *   - prazo de envio vencido: solicitado/rejeitado com prazo_envio < hoje;
 *   - análise parada: recebido há mais de N dias (config prazos.analise_parada_dias).
 *
 * $restritoA: preparação para o portal (versão 2); limita ao cliente informado.
 */
final class Documento extends Model
{
    public const STATUS = [
        'solicitado' => 'Solicitado',
        'recebido'   => 'Recebido',
        'aprovado'   => 'Aprovado',
        'rejeitado'  => 'Rejeitado',
    ];

    /** Status que ainda aguardam o envio pelo cliente. */
    public const NAO_ENVIADOS = ['solicitado', 'rejeitado'];

    public const CANAIS = [
        'email'      => 'E-mail',
        'whatsapp'   => 'WhatsApp',
        'presencial' => 'Presencial',
        'sistema'    => 'Sistema',
    ];

    /** Canais que o escritório pode registrar na versão 1 ('sistema' é do portal). */
    public const CANAIS_MANUAIS = ['email', 'whatsapp', 'presencial'];

    public const PENDENCIAS = [
        'nao_enviados'   => 'Não enviados (solicitados ou rejeitados)',
        'prazo_vencido'  => 'Prazo de envio vencido',
        'analise_parada' => 'Aguardando análise há mais tempo',
    ];

    public const ORDENACOES = [
        'prioridade' => "FIELD(d.status, 'rejeitado', 'solicitado', 'recebido', 'aprovado'),
                         d.prazo_envio IS NULL, d.prazo_envio ASC, d.recebido_em ASC, d.id ASC",
        'recentes'   => 'd.criado_em DESC, d.id DESC',
        'recebimento' => 'd.recebido_em IS NULL, d.recebido_em DESC, d.id DESC',
        'cliente'    => 'c.razao_social ASC, d.id ASC',
    ];

    private const SELECT = 'SELECT d.*,
                                   c.razao_social AS cliente_nome, c.cpf_cnpj AS cliente_documento, c.status AS cliente_status,
                                   o.competencia AS obrigacao_competencia, o.data_vencimento AS obrigacao_vencimento,
                                   o.status AS obrigacao_status, t.nome AS tipo_nome,
                                   ur.nome AS registrado_por_nome, ua.nome AS analisado_por_nome,
                                   DATEDIFF(d.prazo_envio, CURDATE()) AS dias_prazo,
                                   TIMESTAMPDIFF(DAY, d.recebido_em, NOW()) AS dias_aguardando
                              FROM documentos d
                              JOIN clientes c              ON c.id = d.cliente_id
                              LEFT JOIN obrigacoes o       ON o.id = d.obrigacao_id
                              LEFT JOIN tipos_obrigacao t  ON t.id = o.tipo_obrigacao_id
                              LEFT JOIN usuarios ur        ON ur.id = d.registrado_por
                              LEFT JOIN usuarios ua        ON ua.id = d.analisado_por';

    public static function diasAnaliseParada(): int
    {
        return max(1, (int) config('prazos.analise_parada_dias', 3));
    }

    /**
     * Pendência calculada para exibição.
     *
     * @return array{tipo: string, dias: int}|null
     */
    public static function pendencia(array $documento): ?array
    {
        if (in_array($documento['status'], self::NAO_ENVIADOS, true)
            && $documento['dias_prazo'] !== null && (int) $documento['dias_prazo'] < 0
        ) {
            return ['tipo' => 'prazo_vencido', 'dias' => -(int) $documento['dias_prazo']];
        }

        if ($documento['status'] === 'recebido' && (int) $documento['dias_aguardando'] > self::diasAnaliseParada()) {
            return ['tipo' => 'analise_parada', 'dias' => (int) $documento['dias_aguardando']];
        }

        return null;
    }

    public function listar(array $filtros, int $limite, int $offset, ?int $restritoA = null): array
    {
        [$where, $parametros] = $this->condicoes($filtros, $restritoA);
        $ordem = self::ORDENACOES[$filtros['ordenar'] ?? 'prioridade'] ?? self::ORDENACOES['prioridade'];

        return $this->executar(
            self::SELECT . $where . " ORDER BY {$ordem} LIMIT ? OFFSET ?",
            [...$parametros, $limite, $offset]
        )->fetchAll();
    }

    public function contar(array $filtros, ?int $restritoA = null): int
    {
        [$where, $parametros] = $this->condicoes($filtros, $restritoA);

        return (int) $this->executar(
            'SELECT COUNT(*) FROM documentos d JOIN clientes c ON c.id = d.cliente_id' . $where,
            $parametros
        )->fetchColumn();
    }

    /**
     * Contagens para os atalhos da listagem.
     *
     * @return array{solicitado: int, recebido: int, rejeitado: int, aprovado: int, prazo_vencido: int, analise_parada: int}
     */
    public function contarPendencias(?int $restritoA = null): array
    {
        $dias = self::diasAnaliseParada();
        $sql = "SELECT SUM(d.status = 'solicitado') AS solicitado,
                       SUM(d.status = 'recebido')   AS recebido,
                       SUM(d.status = 'rejeitado')  AS rejeitado,
                       SUM(d.status = 'aprovado')   AS aprovado,
                       SUM(d.status IN ('solicitado', 'rejeitado') AND d.prazo_envio < CURDATE()) AS prazo_vencido,
                       SUM(d.status = 'recebido' AND TIMESTAMPDIFF(DAY, d.recebido_em, NOW()) > {$dias}) AS analise_parada
                  FROM documentos d";
        $parametros = [];

        if ($restritoA !== null) {
            $sql .= ' WHERE d.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        return array_map('intval', $this->executar($sql, $parametros)->fetch());
    }

    public function buscarPorId(int $id, ?int $restritoA = null): ?array
    {
        $sql = self::SELECT . ' WHERE d.id = ?';
        $parametros = [$id];

        if ($restritoA !== null) {
            $sql .= ' AND d.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        return $this->executar($sql, $parametros)->fetch() ?: null;
    }

    /**
     * Documentos que interessam ao Radar: ainda não enviados (solicitado ou
     * rejeitado) e aguardando análise (recebido). Uma única consulta, já com
     * cliente, responsável e obrigação vinculada (sem N+1).
     */
    public function listarParaRadar(?int $clienteId = null, ?int $responsavelId = null): array
    {
        $sql = "SELECT d.id, d.cliente_id, d.titulo, d.status, d.prazo_envio, d.recebido_em, d.obrigacao_id,
                       c.razao_social AS cliente_nome, c.status AS cliente_status,
                       c.responsavel_id AS cliente_responsavel_id, u.nome AS cliente_responsavel_nome,
                       t.nome AS tipo_nome, o.competencia AS obrigacao_competencia,
                       DATEDIFF(d.prazo_envio, CURDATE()) AS dias_prazo,
                       TIMESTAMPDIFF(DAY, d.recebido_em, NOW()) AS dias_aguardando
                  FROM documentos d
                  JOIN clientes c             ON c.id = d.cliente_id
                  JOIN usuarios u             ON u.id = c.responsavel_id
                  LEFT JOIN obrigacoes o      ON o.id = d.obrigacao_id
                  LEFT JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
                 WHERE d.status IN ('solicitado', 'rejeitado', 'recebido')";
        $parametros = [];

        if ($clienteId !== null) {
            $sql .= ' AND d.cliente_id = ?';
            $parametros[] = $clienteId;
        }
        if ($responsavelId !== null) {
            $sql .= ' AND c.responsavel_id = ?';
            $parametros[] = $responsavelId;
        }

        return $this->executar($sql . ' ORDER BY d.id', $parametros)->fetchAll();
    }

    /**
     * Obrigações que possuem documentos (opções do filtro da listagem).
     */
    public function listarObrigacoesComDocumentos(?int $restritoA = null): array
    {
        $sql = 'SELECT DISTINCT o.id, o.competencia, t.nome AS tipo_nome, c.razao_social AS cliente_nome
                  FROM documentos d
                  JOIN obrigacoes o      ON o.id = d.obrigacao_id
                  JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
                  JOIN clientes c        ON c.id = o.cliente_id';
        $parametros = [];

        if ($restritoA !== null) {
            $sql .= ' WHERE d.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        return $this->executar($sql . ' ORDER BY c.razao_social, o.competencia DESC, t.nome', $parametros)->fetchAll();
    }

    /**
     * Cria uma solicitação (status 'solicitado', sem arquivo).
     */
    public function solicitar(int $clienteId, ?int $obrigacaoId, string $titulo, ?string $descricao, ?string $prazoEnvio): int
    {
        $this->executar(
            'INSERT INTO documentos (cliente_id, obrigacao_id, titulo, descricao, prazo_envio, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clienteId, $obrigacaoId, $titulo, $descricao, $prazoEnvio, 'solicitado']
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * Edita somente os dados descritivos. Cliente e obrigação não mudam.
     */
    public function atualizarDados(int $id, string $titulo, ?string $descricao, ?string $prazoEnvio): void
    {
        $this->executar(
            'UPDATE documentos SET titulo = ?, descricao = ?, prazo_envio = ? WHERE id = ?',
            [$titulo, $descricao, $prazoEnvio, $id]
        );
    }

    /**
     * Registra o recebimento (de 'solicitado' ou de 'rejeitado').
     * Uma análise anterior (rejeição) é limpa, pois se refere ao arquivo substituído.
     */
    public function registrarRecebimento(
        int $id,
        string $canal,
        string $nomeOriginal,
        string $nomeArmazenado,
        string $mime,
        int $tamanho,
        int $usuarioId,
        string $recebidoEm
    ): bool {
        return $this->executar(
            "UPDATE documentos
                SET status = 'recebido', canal_recebimento = ?, arquivo_original = ?, arquivo_armazenado = ?,
                    mime_type = ?, tamanho_bytes = ?, registrado_por = ?, recebido_em = ?,
                    analisado_por = NULL, analisado_em = NULL, motivo_rejeicao = NULL
              WHERE id = ? AND status IN ('solicitado', 'rejeitado')",
            [$canal, $nomeOriginal, $nomeArmazenado, $mime, $tamanho, $usuarioId, $recebidoEm, $id]
        )->rowCount() > 0;
    }

    public function aprovar(int $id, int $usuarioId): bool
    {
        return $this->executar(
            "UPDATE documentos SET status = 'aprovado', analisado_por = ?, analisado_em = NOW(), motivo_rejeicao = NULL
              WHERE id = ? AND status = 'recebido'",
            [$usuarioId, $id]
        )->rowCount() > 0;
    }

    /**
     * Rejeita mantendo o arquivo e os dados do recebimento.
     */
    public function rejeitar(int $id, int $usuarioId, string $motivo): bool
    {
        return $this->executar(
            "UPDATE documentos SET status = 'rejeitado', analisado_por = ?, analisado_em = NOW(), motivo_rejeicao = ?
              WHERE id = ? AND status = 'recebido'",
            [$usuarioId, $motivo, $id]
        )->rowCount() > 0;
    }

    public function excluir(int $id): bool
    {
        return $this->executar('DELETE FROM documentos WHERE id = ?', [$id])->rowCount() > 0;
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function condicoes(array $filtros, ?int $restritoA): array
    {
        $condicoes  = [];
        $parametros = [];

        if ($restritoA !== null) {
            $condicoes[]  = 'd.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        $busca = $filtros['busca'] ?? '';
        if ($busca !== '') {
            $termo = '%' . addcslashes($busca, '%_\\') . '%';
            $ou = ['d.titulo LIKE ?', 'd.descricao LIKE ?', 'd.arquivo_original LIKE ?', 'c.razao_social LIKE ?', 'c.nome_fantasia LIKE ?'];
            array_push($parametros, $termo, $termo, $termo, $termo, $termo);

            $digitos = (string) preg_replace('/\D/', '', $busca);
            if (strlen($digitos) >= 3) {
                $ou[] = 'c.cpf_cnpj LIKE ?';
                $parametros[] = '%' . $digitos . '%';
            }

            $condicoes[] = '(' . implode(' OR ', $ou) . ')';
        }

        foreach (['cliente_id', 'obrigacao_id'] as $campo) {
            if (($filtros[$campo] ?? 0) > 0) {
                $condicoes[]  = "d.{$campo} = ?";
                $parametros[] = $filtros[$campo];
            }
        }

        foreach (['status', 'canal_recebimento'] as $campo) {
            if (($filtros[$campo] ?? '') !== '') {
                $condicoes[]  = "d.{$campo} = ?";
                $parametros[] = $filtros[$campo];
            }
        }

        $dias = self::diasAnaliseParada();
        $pendencia = match ($filtros['pendencia'] ?? '') {
            // Uso interno (dashboard): tudo que ainda exige ação, exceto aprovados.
            // Não consta em PENDENCIAS, então não pode ser enviado pela URL da listagem.
            'aguardando'     => "d.status IN ('solicitado', 'rejeitado', 'recebido')",
            'nao_enviados'   => "d.status IN ('solicitado', 'rejeitado')",
            'prazo_vencido'  => "d.status IN ('solicitado', 'rejeitado') AND d.prazo_envio < CURDATE()",
            'analise_parada' => "d.status = 'recebido' AND TIMESTAMPDIFF(DAY, d.recebido_em, NOW()) > {$dias}",
            default          => null,
        };
        if ($pendencia !== null) {
            $condicoes[] = '(' . $pendencia . ')';
        }

        return [$condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes), $parametros];
    }
}
