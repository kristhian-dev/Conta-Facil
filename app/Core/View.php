<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Renderização das Views (arquivos PHP em app/Views).
 * A view é renderizada primeiro; o resultado é inserido no layout
 * na variável $conteudo.
 */
final class View
{
    private const FORMATO_NOME = '#^[a-z0-9_]+(/[a-z0-9_]+)*$#';

    public static function exibir(string $view, array $dados = [], ?string $layout = 'layouts/main'): void
    {
        $conteudo = self::renderizar($view, $dados);

        echo $layout === null
            ? $conteudo
            : self::renderizar($layout, ['conteudo' => $conteudo] + $dados);
    }

    public static function renderizar(string $view, array $dados = []): string
    {
        if (!preg_match(self::FORMATO_NOME, $view)) {
            throw new InvalidArgumentException("Nome de view inválido: {$view}");
        }

        $arquivo = APP_ROOT . '/app/Views/' . $view . '.php';
        if (!is_file($arquivo)) {
            throw new RuntimeException("View não encontrada: {$view}");
        }

        // Escopo isolado: a view enxerga apenas as variáveis de $dados.
        $renderizar = static function (string $__arquivo, array $__dados): string {
            extract($__dados, EXTR_SKIP);
            ob_start();
            try {
                require $__arquivo;
                return (string) ob_get_clean();
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        };

        return $renderizar($arquivo, $dados);
    }
}
