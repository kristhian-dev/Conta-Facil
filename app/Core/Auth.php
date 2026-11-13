<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

use App\Models\Usuario;

/**
 * Autenticação e informações do usuário logado.
 *
 * - Senhas verificadas com password_verify (hash gerado por password_hash).
 * - Somente perfis listados em config('auth.perfis_ativos') podem entrar;
 *   na versão 1 o perfil 'cliente' é recusado.
 * - O usuário é recarregado do banco a cada requisição, para que um usuário
 *   desativado ou com perfil alterado perca o acesso imediatamente.
 */
final class Auth
{
    public const CHAVE_SESSAO = '_usuario_id';

    /**
     * Hash bcrypt de uma senha aleatória descartada. Usado quando o e-mail
     * não existe, para que o login faça sempre uma única verificação bcrypt
     * e o tempo de resposta não revele quais e-mails estão cadastrados.
     */
    private const HASH_FICTICIO = '$2y$10$n7wpOBjpT0DAHVeH6cpc6.SXwpzY7JK9UsSGrkiuriToJ2PUGLycO';

    private static ?array $usuario = null;
    private static bool $carregado = false;

    /**
     * Tenta autenticar. Retorna false para qualquer falha, sem revelar
     * se o e-mail existe, se a senha está errada ou se o perfil está bloqueado.
     */
    public static function tentarLogin(string $email, string $senha): bool
    {
        $model   = new Usuario();
        $usuario = $model->buscarPorEmail(mb_strtolower(trim($email)));

        // Sempre executa exatamente uma verificação, exista ou não o e-mail.
        $senhaOk = password_verify($senha, $usuario['senha_hash'] ?? self::HASH_FICTICIO);

        if ($usuario === null || !$senhaOk || !self::podeAcessar($usuario)) {
            return false;
        }

        $id = (int) $usuario['id'];

        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            $model->atualizarSenhaHash($id, password_hash($senha, PASSWORD_DEFAULT));
        }

        Session::regenerar();
        Session::set(self::CHAVE_SESSAO, $id);
        $model->registrarLogin($id);

        unset($usuario['senha_hash']);
        self::$usuario   = $usuario;
        self::$carregado = true;

        return true;
    }

    public static function logout(): void
    {
        Session::renovar();
        self::$usuario   = null;
        self::$carregado = true;
    }

    /**
     * Dados do usuário logado (sem o hash da senha) ou null.
     */
    public static function usuario(): ?array
    {
        if (self::$carregado) {
            return self::$usuario;
        }

        self::$carregado = true;
        $id = Session::get(self::CHAVE_SESSAO);

        if (!is_int($id)) {
            return null;
        }

        $usuario = (new Usuario())->buscarPorId($id);

        if ($usuario === null || !self::podeAcessar($usuario)) {
            // Usuário removido, desativado ou com perfil bloqueado.
            Session::remover(self::CHAVE_SESSAO);
            return null;
        }

        unset($usuario['senha_hash']);
        self::$usuario = $usuario;

        return self::$usuario;
    }

    public static function verificar(): bool
    {
        return self::usuario() !== null;
    }

    public static function id(): ?int
    {
        $usuario = self::usuario();

        return $usuario ? (int) $usuario['id'] : null;
    }

    public static function perfil(): ?string
    {
        return self::usuario()['perfil'] ?? null;
    }

    /**
     * @param string[] $perfis
     */
    public static function temPerfil(array $perfis): bool
    {
        $perfil = self::perfil();

        return $perfil !== null && in_array($perfil, $perfis, true);
    }

    /**
     * Restrição de dados por cliente (preparação para o portal da versão 2).
     *
     * Retorna null quando o usuário pode ver todos os clientes (admin e
     * contador) ou o cliente_id ao qual o usuário está vinculado (perfil
     * 'cliente'). Os Models devem aplicar "AND cliente_id = :escopo"
     * sempre que o valor não for null.
     */
    public static function clienteRestrito(): ?int
    {
        $usuario = self::usuario();

        if ($usuario !== null && $usuario['perfil'] === 'cliente') {
            return (int) $usuario['cliente_id'];
        }

        return null;
    }

    private static function podeAcessar(array $usuario): bool
    {
        return (int) $usuario['ativo'] === 1
            && in_array($usuario['perfil'], config('auth.perfis_ativos', []), true);
    }
}
