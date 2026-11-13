<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

/**
 * Gerenciamento da sessão PHP com configurações seguras,
 * expiração por inatividade e mensagens flash.
 */
final class Session
{
    private const CHAVE_ATIVIDADE = '_ultima_atividade';
    private const CHAVE_FLASH     = '_flash';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name(config('sessao.nome'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path(),
            'secure'   => self::conexaoSegura(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        self::controlarInatividade();
    }

    public static function get(string $chave, mixed $padrao = null): mixed
    {
        return $_SESSION[$chave] ?? $padrao;
    }

    public static function set(string $chave, mixed $valor): void
    {
        $_SESSION[$chave] = $valor;
    }

    public static function remover(string $chave): void
    {
        unset($_SESSION[$chave]);
    }

    /**
     * Gera novo ID de sessão (usar no login, contra fixação de sessão).
     */
    public static function regenerar(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Encerra a sessão atual (logout ou expiração): apaga todos os dados,
     * remove o arquivo da sessão antiga no servidor e emite um novo ID.
     * A nova sessão começa vazia, o que permite exibir uma mensagem flash
     * (ex.: "Você saiu do sistema") após o redirecionamento.
     */
    public static function renovar(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /**
     * Mensagem exibida uma única vez (após redirecionamento).
     * Tipos sugeridos: sucesso, erro, aviso.
     */
    public static function flash(string $tipo, string $mensagem): void
    {
        $_SESSION[self::CHAVE_FLASH][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    /**
     * Retorna e limpa as mensagens flash pendentes.
     */
    public static function consumirFlash(): array
    {
        $mensagens = $_SESSION[self::CHAVE_FLASH] ?? [];
        unset($_SESSION[self::CHAVE_FLASH]);

        return $mensagens;
    }

    private static function controlarInatividade(): void
    {
        $agora  = time();
        $ultima = $_SESSION[self::CHAVE_ATIVIDADE] ?? null;

        if ($ultima !== null && ($agora - $ultima) > config('sessao.tempo_inatividade')) {
            // Sessão expirada: limpa os dados e gera um novo ID.
            $estavaLogado = isset($_SESSION[Auth::CHAVE_SESSAO]);
            self::renovar();

            if ($estavaLogado) {
                self::flash('aviso', 'Sua sessão expirou por inatividade. Entre novamente.');
            }
        }

        $_SESSION[self::CHAVE_ATIVIDADE] = $agora;
    }

    private static function conexaoSegura(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
