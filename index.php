<?php

declare(strict_types=1);

/**
 * ContaFácil — ponto de entrada único da aplicação.
 *
 * Todas as requisições passam por aqui: index.php?r=<rota>
 */

define('APP_ROOT', __DIR__);

require APP_ROOT . '/app/bootstrap.php';

use App\Core\HttpException;
use App\Core\Router;
use App\Core\View;

try {
    $router = new Router(require APP_ROOT . '/config/routes.php');
    $router->despachar(
        (string) ($_GET['r'] ?? config('app.rota_inicial')),
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );
} catch (HttpException $e) {
    http_response_code($e->getCode());
    View::exibir('errors/erro', ['status' => $e->getCode(), 'mensagem' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[ContaFácil] ' . $e);
    http_response_code(500);
    View::exibir('errors/erro', [
        'status'   => 500,
        'mensagem' => config('app.debug') ? $e->getMessage() : 'Ocorreu um erro interno. Tente novamente mais tarde.',
    ]);
}
