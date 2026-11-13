<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

/**
 * Proteção contra CSRF: um token aleatório por sessão, enviado em todo
 * formulário POST (campo _csrf) e conferido pelo Router.
 */
final class Csrf
{
    public const CAMPO = '_csrf';
    private const CHAVE_SESSAO = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::CHAVE_SESSAO);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::CHAVE_SESSAO, $token);
        }

        return $token;
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::CAMPO . '" value="' . e(self::token()) . '">';
    }

    public static function validar(?string $tokenRecebido): bool
    {
        $tokenSessao = Session::get(self::CHAVE_SESSAO);

        return is_string($tokenSessao)
            && is_string($tokenRecebido)
            && $tokenSessao !== ''
            && hash_equals($tokenSessao, $tokenRecebido);
    }

    /**
     * Valida o token da requisição atual (campo do formulário ou cabeçalho
     * X-CSRF-Token, para chamadas via JavaScript).
     */
    public static function validarRequisicao(): bool
    {
        $token = $_POST[self::CAMPO] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        return self::validar(is_string($token) ? $token : null);
    }
}
