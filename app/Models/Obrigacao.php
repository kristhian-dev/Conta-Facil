<?php

declare(strict_types=1);

namespace App\Models;

defined('APP_ROOT') || exit;

use App\Core\Model;

/**
 * Acesso à tabela obrigacoes.
 *
 * Situação x prazo:
 *   - "status" é o que está gravado (pendente, em_andamento, concluida, cancelada).
 *   - "prazo" é CALCULADO a partir da data de vencimento e de hoje, e só existe
 *     para obrigações em aberto: atrasada, vence_hoje, proxima (até N dias) e no_prazo.
 *   Nada sobre prazo é gravado no banco. A data de hoje vem do MySQL (CURDATE()),
 *   para que filtros e destaques usem exatamente a mesma referência.
 *
 * $restritoA: preparação para o portal (versão 2); limita ao cliente informado.
 */
final class Obrigacao extends Model
{
    public const STATUS = [
        'pendente'     => 'Pendente',
        'em_andamento' => 'Em andamento',
        'concluida'    => 'Concluída',
        'cancelada'    => 'Cancelada',
    ];

    /** Status considerados "em aberto" (ainda exigem ação). */
    public const STATUS_ABERTOS = ['pendente', 'em_andamento'];

    public const PRAZOS = [
        'atrasada'   => 'Atrasadas',
        'vence_hoje' => 'Vencem hoje',
        'proxima'    => 'Próximas do vencimento',
        'em_aberto'  => 'Todas em aberto',
    ];

    public const ORDENACOES = [
        // Em aberto primeiro, depois pelo vencimento mais próximo.
        'prioridade'      => "(o.status IN ('concluida', 'cancelada')) ASC, o.data_vencimento ASC, o.id ASC",
        'vencimento'      => 'o.data_vencimento ASC, o.id ASC',
        'vencimento_desc' => 'o.data_vencimento DESC, o.id DESC',
        'competencia'     => 'o.competencia DESC, c.razao_social ASC, o.id ASC',
        'cliente'         => 'c.razao_social ASC, o.data_vencimento ASC, o.id ASC',
    ];

    private const ABERTA = "o.status IN ('pendente', 'em_andamento')";

    private const CAMPOS = [
        'cliente_id', 'tipo_obrigacao_id', 'competencia', 'data_vencimento', 'status',
        'data_conclusao', 'valor', 'responsavel_id', 'observacoes',
    ];

    private const SELECT = 'SELECT o.*,
                                   c.razao_social AS cliente_nome, c.nome_fantasia AS cliente_fantasia,
                                   c.cpf_cnpj AS cliente_documento, c.status AS cliente_status,
                                   t.nome AS tipo_nome, t.descricao AS tipo_descricao, t.periodicidade AS tipo_periodicidade,
                                   u.nome AS responsavel_nome,
                                   DATEDIFF(o.data_vencimento, CURDATE()) AS dias_para_vencer
                              FROM obrigacoes o
                              JOIN clientes c        ON c.id = o.cliente_id
                              JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
                              JOIN usuarios u        ON u.id = o.responsavel_id';

    /**
     * Situação de prazo calculada (mesmas regras dos filtros SQL).
     * Retorna null para obrigações concluídas ou canceladas.
     */
    public static function prazo(array $obrigacao): ?string
    {
        if (!in_array($obrigacao['status'], self::STATUS_ABERTOS, true)) {
            return null;
        }

        $dias = (int) $obrigacao['dias_para_vencer'];

        return match (true) {
            $dias < 0                => 'atrasada',
            $dias === 0              => 'vence_hoje',
            $dias <= self::janela()  => 'proxima',
            default                  => 'no_prazo',
        };
    }

    public static function janela(): int
    {
        return max(1, (int) config('prazos.janela_alerta_dias', 7));
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
            'SELECT COUNT(*) FROM obrigacoes o JOIN clientes c ON c.id = o.cliente_id
                                               JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id' . $where,
            $parametros
        )->fetchColumn();
    }

    /**
     * Quantidade de obrigações em aberto por situação de prazo (atalhos da listagem).
     *
     * @return array{atrasada: int, vence_hoje: int, proxima: int}
     */
    public function contarPorPrazo(?int $restritoA = null): array
    {
        return array_intersect_key(
            $this->contarIndicadores($restritoA),
            array_flip(['atrasada', 'vence_hoje', 'proxima'])
        );
    }

    /**
     * Indicadores gerais em UMA consulta (dashboard e atalhos da listagem).
     * As condições de prazo são as mesmas dos filtros (condicoes()) e de prazo():
     *   em_aberto  = pendente ou em andamento (canceladas não entram);
     *   atrasada   = em aberto com vencimento antes de hoje;
     *   vence_hoje = em aberto com vencimento hoje;
     *   proxima    = em aberto vencendo de amanhã até hoje + janela.
     *
     * @return array{em_aberto: int, atrasada: int, vence_hoje: int, proxima: int, concluida: int, cancelada: int, total: int}
     */
    public function contarIndicadores(?int $restritoA = null): array
    {
        $janela = self::janela();
        $aberta = self::ABERTA;
        $sql = "SELECT
                    SUM({$aberta}) AS em_aberto,
                    SUM({$aberta} AND o.data_vencimento < CURDATE()) AS atrasada,
                    SUM({$aberta} AND o.data_vencimento = CURDATE()) AS vence_hoje,
                    SUM({$aberta} AND o.data_vencimento BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL {$janela} DAY) AS proxima,
                    SUM(o.status = 'concluida') AS concluida,
                    SUM(o.status = 'cancelada') AS cancelada,
                    COUNT(*) AS total
                  FROM obrigacoes o";
        $parametros = [];

        if ($restritoA !== null) {
            $sql .= ' WHERE o.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        return array_map('intval', $this->executar($sql, $parametros)->fetch());
    }

    public function buscarPorId(int $id, ?int $restritoA = null): ?array
    {
        $sql = self::SELECT . ' WHERE o.id = ?';
        $parametros = [$id];

        if ($restritoA !== null) {
            $sql .= ' AND o.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        return $this->executar($sql, $parametros)->fetch() ?: null;
    }

    /**
     * Obrigações que interessam ao Radar: em aberto e com vencimento até
     * hoje + janela de alerta (inclui as atrasadas). Uma única consulta,
     * já com os dados do cliente e do seu responsável (sem N+1).
     */
    public function listarParaRadar(?int $clienteId = null, ?int $responsavelId = null): array
    {
        $janela = self::janela();
        $sql = 'SELECT o.id, o.cliente_id, o.competencia, o.data_vencimento, o.status, t.nome AS tipo_nome,
                       c.razao_social AS cliente_nome, c.status AS cliente_status,
                       c.responsavel_id AS cliente_responsavel_id, u.nome AS cliente_responsavel_nome,
                       DATEDIFF(o.data_vencimento, CURDATE()) AS dias_para_vencer
                  FROM obrigacoes o
                  JOIN clientes c        ON c.id = o.cliente_id
                  JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
                  JOIN usuarios u        ON u.id = c.responsavel_id
                 WHERE ' . self::ABERTA . " AND o.data_vencimento <= CURDATE() + INTERVAL {$janela} DAY";
        $parametros = [];

        if ($clienteId !== null) {
            $sql .= ' AND o.cliente_id = ?';
            $parametros[] = $clienteId;
        }
        if ($responsavelId !== null) {
            $sql .= ' AND c.responsavel_id = ?';
            $parametros[] = $responsavelId;
        }

        return $this->executar($sql . ' ORDER BY o.data_vencimento, o.id', $parametros)->fetchAll();
    }

    /**
     * Obrigações que podem receber documentos (opções de formulário):
     * não canceladas, de clientes ativos.
     *
     * @return array<int, array{id: int, cliente_id: int, competencia: string, data_vencimento: string, status: string, tipo_nome: string}>
     */
    public function listarParaSelecao(?int $restritoA = null): array
    {
        $sql = "SELECT o.id, o.cliente_id, o.competencia, o.data_vencimento, o.status, t.nome AS tipo_nome
                  FROM obrigacoes o
                  JOIN tipos_obrigacao t ON t.id = o.tipo_obrigacao_id
                  JOIN clientes c        ON c.id = o.cliente_id
                 WHERE o.status <> 'cancelada' AND c.status = 'ativo'";
        $parametros = [];

        if ($restritoA !== null) {
            $sql .= ' AND o.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        return $this->executar($sql . ' ORDER BY o.competencia DESC, t.nome', $parametros)->fetchAll();
    }

    /**
     * Verifica a restrição única (cliente, tipo, competência).
     */
    public function existeDuplicada(int $clienteId, int $tipoId, string $competencia, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT 1 FROM obrigacoes WHERE cliente_id = ? AND tipo_obrigacao_id = ? AND competencia = ?';
        $parametros = [$clienteId, $tipoId, $competencia];

        if ($ignorarId !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $ignorarId;
        }

        return (bool) $this->executar($sql . ' LIMIT 1', $parametros)->fetchColumn();
    }

    public function criar(array $dados): int
    {
        $colunas = implode(', ', self::CAMPOS);
        $marcadores = implode(', ', array_fill(0, count(self::CAMPOS), '?'));

        $this->executar("INSERT INTO obrigacoes ({$colunas}) VALUES ({$marcadores})", $this->valores($dados));

        return (int) $this->db->lastInsertId();
    }

    public function atualizar(int $id, array $dados): void
    {
        $atribuicoes = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", self::CAMPOS));

        $this->executar("UPDATE obrigacoes SET {$atribuicoes} WHERE id = ?", [...$this->valores($dados), $id]);
    }

    /**
     * Altera somente a situação. A data de conclusão acompanha o status
     * (obrigatória para "concluida", nula para os demais), como exige o banco.
     */
    public function alterarSituacao(int $id, string $status, ?string $dataConclusao = null): void
    {
        $this->executar(
            'UPDATE obrigacoes SET status = ?, data_conclusao = ? WHERE id = ?',
            [$status, $status === 'concluida' ? $dataConclusao : null, $id]
        );
    }

    /**
     * Documentos vinculados (impedem a exclusão e a troca de cliente).
     */
    public function contarDocumentos(int $id): int
    {
        return (int) $this->executar('SELECT COUNT(*) FROM documentos WHERE obrigacao_id = ?', [$id])->fetchColumn();
    }

    public function excluir(int $id): bool
    {
        return $this->executar('DELETE FROM obrigacoes WHERE id = ?', [$id])->rowCount() > 0;
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function condicoes(array $filtros, ?int $restritoA): array
    {
        $condicoes  = [];
        $parametros = [];

        if ($restritoA !== null) {
            $condicoes[]  = 'o.cliente_id = ?';
            $parametros[] = $restritoA;
        }

        $busca = $filtros['busca'] ?? '';
        if ($busca !== '') {
            $termo = '%' . addcslashes($busca, '%_\\') . '%';
            $ou = ['c.razao_social LIKE ?', 'c.nome_fantasia LIKE ?', 't.nome LIKE ?', 'o.observacoes LIKE ?'];
            array_push($parametros, $termo, $termo, $termo, $termo);

            $digitos = (string) preg_replace('/\D/', '', $busca);
            if (strlen($digitos) >= 3) {
                $ou[] = 'c.cpf_cnpj LIKE ?';
                $parametros[] = '%' . $digitos . '%';
            }

            $condicoes[] = '(' . implode(' OR ', $ou) . ')';
        }

        foreach (['cliente_id', 'tipo_obrigacao_id', 'responsavel_id'] as $campo) {
            if (($filtros[$campo] ?? 0) > 0) {
                $condicoes[]  = "o.{$campo} = ?";
                $parametros[] = $filtros[$campo];
            }
        }

        if (($filtros['status'] ?? '') !== '') {
            $condicoes[]  = 'o.status = ?';
            $parametros[] = $filtros['status'];
        }

        if (($filtros['competencia'] ?? '') !== '') {
            $condicoes[]  = 'o.competencia = ?';
            $parametros[] = $filtros['competencia'] . '-01';
        }

        if (($filtros['vencimento_de'] ?? '') !== '') {
            $condicoes[]  = 'o.data_vencimento >= ?';
            $parametros[] = $filtros['vencimento_de'];
        }

        if (($filtros['vencimento_ate'] ?? '') !== '') {
            $condicoes[]  = 'o.data_vencimento <= ?';
            $parametros[] = $filtros['vencimento_ate'];
        }

        $janela = self::janela();
        $condicaoPrazo = match ($filtros['prazo'] ?? '') {
            'atrasada'   => self::ABERTA . ' AND o.data_vencimento < CURDATE()',
            'vence_hoje' => self::ABERTA . ' AND o.data_vencimento = CURDATE()',
            'proxima'    => self::ABERTA . " AND o.data_vencimento BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL {$janela} DAY",
            'em_aberto'  => self::ABERTA,
            default      => null,
        };
        if ($condicaoPrazo !== null) {
            $condicoes[] = '(' . $condicaoPrazo . ')';
        }

        return [$condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes), $parametros];
    }

    private function valores(array $dados): array
    {
        return array_map(static fn (string $campo): mixed => $dados[$campo] ?? null, self::CAMPOS);
    }
}
