<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

/**
 * Base dos Controllers: atalhos para exibir views, redirecionar e ler a entrada.
 * Controllers não escrevem SQL (usam Models/Services) nem HTML (usam Views).
 */
abstract class Controller
{
    protected function view(string $view, array $dados = [], ?string $layout = 'layouts/main'): void
    {
        View::exibir($view, $dados, $layout);
    }

    /**
     * Redireciona para outra rota (padrão Post/Redirect/Get).
     */
    protected function redirecionar(string $rota, array $parametros = []): never
    {
        redirecionar($rota, $parametros);
    }

    protected function ehPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Texto da query string (GET), sem espaços nas pontas.
     * Valores que não são texto (ex.: arrays) viram ''.
     */
    protected function query(string $campo): string
    {
        return self::texto($_GET[$campo] ?? '');
    }

    /**
     * Texto do corpo do formulário (POST), sem espaços nas pontas.
     */
    protected function post(string $campo): string
    {
        return self::texto($_POST[$campo] ?? '');
    }

    /**
     * Converte um identificador recebido em inteiro positivo.
     * Qualquer valor inválido resulta em 404.
     */
    protected function id(string $valor): int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            throw new HttpException(404);
        }

        return $id;
    }

    private static function texto(mixed $valor): string
    {
        return is_string($valor) ? trim($valor) : '';
    }
}
