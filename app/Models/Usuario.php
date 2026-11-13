<?php

declare(strict_types=1);

namespace App\Models;

defined('APP_ROOT') || exit;

use App\Core\Model;

/**
 * Acesso à tabela usuarios.
 * Contém o necessário para a autenticação e para a escolha do responsável
 * por clientes; o CRUD de usuários será adicionado no módulo correspondente.
 */
final class Usuario extends Model
{
    private const COLUNAS = 'id, nome, email, senha_hash, perfil, cliente_id, ativo, ultimo_login';

    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUNAS . ' FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return $stmt->fetch() ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ' . self::COLUNAS . ' FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Usuários que podem ser responsáveis por clientes: admin e contador ativos.
     * $incluirId mantém na lista o responsável atual de um registro em edição,
     * mesmo que ele tenha sido desativado depois.
     *
     * @return array<int, array{id: int, nome: string}>
     */
    public function listarResponsaveis(?int $incluirId = null): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, nome FROM usuarios
              WHERE (ativo = 1 AND perfil IN ('admin', 'contador')) OR id = ?
              ORDER BY nome"
        );
        $stmt->execute([$incluirId ?? 0]);

        return $stmt->fetchAll();
    }

    public function registrarLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function atualizarSenhaHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }
}
