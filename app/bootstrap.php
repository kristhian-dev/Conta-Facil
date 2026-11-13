<?php

declare(strict_types=1);

/**
 * Inicialização da aplicação: autoload, configuração, ambiente e sessão.
 */

defined('APP_ROOT') || exit;

// Autoload: App\Core\Router -> app/Core/Router.php
spl_autoload_register(static function (string $classe): void {
    $prefixo = 'App\\';
    if (!str_starts_with($classe, $prefixo)) {
        return;
    }

    $arquivo = APP_ROOT . '/app/' . str_replace('\\', '/', substr($classe, strlen($prefixo))) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

require APP_ROOT . '/app/Core/helpers.php';

// Ambiente
date_default_timezone_set(config('app.timezone'));
mb_internal_encoding('UTF-8');

ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Cabeçalhos de segurança (definidos no PHP para não depender do mod_headers).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

App\Core\Session::iniciar();
