<?php

declare(strict_types=1);

namespace App\Models;

defined('APP_ROOT') || exit;

use App\Core\Model;

/**
 * Acesso à tabela clientes.
 *
 * $restritoA (preparação para o portal da versão 2): quando não é null,
 * as consultas ficam limitadas ao cliente informado. Os controllers passam
 * Auth::clienteRestrito(), que na versão 1 é sempre null (admin/contador).
 */
final class Cliente extends Model
{
    /** Valores dos ENUMs da tabela (chave = valor no banco, valor = rótulo). */
    public const TIPOS = ['PJ' => 'Pessoa jurídica', 'PF' => 'Pessoa física'];
    public const STATUS = ['ativo' => 'Ativo', 'inativo' => 'Inativo'];
    public const REGIMES_PJ = ['MEI', 'Simples Nacional', 'Lucro Presumido', 'Lucro Real'];
    public const REGIME_PF = 'Pessoa Física';

    /** Ordenações permitidas (lista fechada: o usuário nunca envia SQL). */
    public const ORDENACOES = [
        'nome'     => 'c.razao_social ASC, c.id ASC',
        'recentes' => 'c.criado_em DESC, c.id DESC',
    ];

    /** Colunas gravadas por criar() e atualizar(). */
    private const CAMPOS = [
        'razao_social', 'nome_fantasia', 'tipo_pessoa', 'cpf_cnpj', 'regime_tributario',
        'email', 'telefone', 'responsavel_id', 'status', 'observacoes',
    ];

    private const SELECT = 'SELECT c.*, u.nome AS responsavel_nome
                              FROM clientes c
                              JOIN usuarios u ON u.id = c.responsavel_id';

    /**
     * @param array{busca?: string, tipo_pessoa?: string, regime_tributario?: string,
     *              status?: string, responsavel_id?: int, ordenar?: string} $filtros
     */
    public function listar(array $filtros, int $limite, int $offset, ?int $restritoA = null): array
    {
        [$where, $parametros] = $this->condicoes($filtros, $restritoA);
        $ordem = self::ORDENACOES[$filtros['ordenar'] ?? 'nome'] ?? self::ORDENACOES['nome'];

        $sql = self::SELECT . $where . " ORDER BY {$ordem} LIMIT ? OFFSET ?";

        return $this->executar($sql, [...$parametros, $limite, $offset])->fetchAll();
    }

    public function contar(array $filtros, ?int $restritoA = null): int
    {
        [$where, $parametros] = $this->condicoes($filtros, $restritoA);

        return (int) $this->executar('SELECT COUNT(*) FROM clientes c' . $where, $parametros)->fetchColumn();
    }

    public function buscarPorId(int $id, ?int $restritoA = null): ?array
    {
        if ($restritoA !== null && $restritoA !== $id) {
            return null;
        }

        return $this->executar(self::SELECT . ' WHERE c.id = ?', [$id])->fetch() ?: null;
    }

    /**
     * Totais por situação em uma consulta (dashboard).
     *
     * @return array{total: int, ativos: int, inativos: int}
     */
    public function contarPorStatus(?int $restritoA = null): array
    {
        $sql = "SELECT COUNT(*) AS total, SUM(status = 'ativo') AS ativos, SUM(status = 'inativo') AS inativos FROM clientes";
        $parametros = [];

        if ($restritoA !== null) {
            $sql .= ' WHERE id = ?';
            $parametros[] = $restritoA;
        }

        return array_map('intval', $this->executar($sql, $parametros)->fetch());
    }

    /**
     * Lista resumida para campos de seleção (ex.: cliente de uma obrigação).
     * Por padrão traz só clientes ativos; $incluirId mantém o cliente atual
     * de um registro em edição mesmo que esteja inativo.
     *
     * @return array<int, array{id: int, razao_social: string, cpf_cnpj: string, status: string}>
     */
    public function listarParaSelecao(bool $incluirInativos = false, ?int $incluirId = null, ?int $restritoA = null): array
    {
        $sql = 'SELECT id, razao_social, cpf_cnpj, status FROM clientes WHERE (status = ? OR ? = 1 OR id = ?)';
        $parametros = ['ativo', $incluirInativos ? 1 : 0, $incluirId ?? 0];

        if ($restritoA !== null) {
            $sql .= ' AND id = ?';
            $parametros[] = $restritoA;
        }

        return $this->executar($sql . ' ORDER BY razao_social', $parametros)->fetchAll();
    }

    /**
     * Verifica se o CPF/CNPJ já pertence a outro cliente.
     */
    public function cpfCnpjEmUso(string $cpfCnpj, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT 1 FROM clientes WHERE cpf_cnpj = ?';
        $parametros = [$cpfCnpj];

        if ($ignorarId !== null) {
            $sql .= ' AND id <> ?';
            $parametros[] = $ignorarId;
        }

        return (bool) $this->executar($sql . ' LIMIT 1', $parametros)->fetchColumn();
    }

    /**
     * @param array<string, mixed> $dados valores já validados e normalizados
     */
    public function criar(array $dados): int
    {
        $colunas = implode(', ', self::CAMPOS);
        $marcadores = implode(', ', array_fill(0, count(self::CAMPOS), '?'));

        $this->executar(
            "INSERT INTO clientes ({$colunas}) VALUES ({$marcadores})",
            $this->valores($dados)
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $dados valores já validados e normalizados
     */
    public function atualizar(int $id, array $dados): void
    {
        $atribuicoes = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", self::CAMPOS));

        $this->executar(
            "UPDATE clientes SET {$atribuicoes} WHERE id = ?",
            [...$this->valores($dados), $id]
        );
    }

    /**
     * Quantidade de registros que impedem a exclusão do cliente
     * (as chaves estrangeiras usam ON DELETE RESTRICT).
     *
     * @return array{obrigacoes: int, documentos: int, usuarios: int}
     */
    public function contarVinculos(int $id): array
    {
        $linha = $this->executar(
            'SELECT (SELECT COUNT(*) FROM obrigacoes WHERE cliente_id = ?) AS obrigacoes,
                    (SELECT COUNT(*) FROM documentos WHERE cliente_id = ?) AS documentos,
                    (SELECT COUNT(*) FROM usuarios   WHERE cliente_id = ?) AS usuarios',
            [$id, $id, $id]
        )->fetch();

        return array_map('intval', $linha);
    }

    public function excluir(int $id): bool
    {
        return $this->executar('DELETE FROM clientes WHERE id = ?', [$id])->rowCount() > 0;
    }

    /**
     * Monta o WHERE a partir dos filtros (sempre com parâmetros).
     *
     * @return array{0: string, 1: array}
     */
    private function condicoes(array $filtros, ?int $restritoA): array
    {
        $condicoes  = [];
        $parametros = [];

        if ($restritoA !== null) {
            $condicoes[]  = 'c.id = ?';
            $parametros[] = $restritoA;
        }

        $busca = $filtros['busca'] ?? '';
        if ($busca !== '') {
            // Escapa os curingas do LIKE para que % e _ sejam buscados literalmente.
            $termo = '%' . addcslashes($busca, '%_\\') . '%';
            $ou = ['c.razao_social LIKE ?', 'c.nome_fantasia LIKE ?'];
            array_push($parametros, $termo, $termo);

            // CPF/CNPJ é gravado só com dígitos: aceita a busca com ou sem máscara.
            $digitos = (string) preg_replace('/\D/', '', $busca);
            if (strlen($digitos) >= 3) {
                $ou[] = 'c.cpf_cnpj LIKE ?';
                $parametros[] = '%' . $digitos . '%';
            }

            $condicoes[] = '(' . implode(' OR ', $ou) . ')';
        }

        foreach (['tipo_pessoa', 'regime_tributario', 'status'] as $campo) {
            if (($filtros[$campo] ?? '') !== '') {
                $condicoes[]  = "c.{$campo} = ?";
                $parametros[] = $filtros[$campo];
            }
        }

        if (($filtros['responsavel_id'] ?? 0) > 0) {
            $condicoes[]  = 'c.responsavel_id = ?';
            $parametros[] = $filtros['responsavel_id'];
        }

        $where = $condicoes === [] ? '' : ' WHERE ' . implode(' AND ', $condicoes);

        return [$where, $parametros];
    }

    /**
     * Valores na mesma ordem de self::CAMPOS.
     */
    private function valores(array $dados): array
    {
        return array_map(static fn (string $campo): mixed => $dados[$campo] ?? null, self::CAMPOS);
    }
}
