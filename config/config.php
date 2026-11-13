<?php

declare(strict_types=1);

/**
 * Configuração central do ContaFácil.
 *
 * Valores sensíveis ou específicos do ambiente vêm do arquivo .env
 * (leitor próprio, sem dependências externas).
 * Acesso no código: config('db.host'), config('auth.perfis_ativos') etc.
 */

defined('APP_ROOT') || exit;

/**
 * Lê um arquivo .env no formato CHAVE=valor.
 * - Linhas vazias e iniciadas por # são ignoradas.
 * - Apenas o primeiro "=" separa chave e valor, então o valor pode conter
 *   "=", "#" ou ";" (comum em senhas).
 * - Aspas simples ou duplas em volta do valor são removidas.
 */
$lerEnv = static function (string $arquivo): array {
    if (!is_file($arquivo)) {
        return [];
    }

    $valores = [];
    foreach (file($arquivo, FILE_IGNORE_NEW_LINES) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $valor = trim($valor);

        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[-1] === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }

        $valores[trim($chave)] = $valor;
    }

    return $valores;
};

$valoresEnv = $lerEnv(APP_ROOT . '/.env');

$env = static fn (string $chave, string $padrao = ''): string => (string) ($valoresEnv[$chave] ?? $padrao);

return [
    'app' => [
        'nome'         => 'ContaFácil',
        'debug'        => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
        'timezone'     => $env('APP_TIMEZONE', 'America/Fortaleza'),
        // Rota usada quando ?r= não é informado.
        'rota_inicial' => 'home',
    ],

    'db' => [
        'host'    => $env('DB_HOST', '127.0.0.1'),
        'port'    => (int) $env('DB_PORT', '3306'),
        'nome'    => $env('DB_NAME', 'contafacil'),
        'usuario' => $env('DB_USER', 'root'),
        'senha'   => $env('DB_PASS', ''),
    ],

    'sessao' => [
        'nome'                => 'CONTAFACIL_SESSID',
        // Encerra a sessão após este tempo sem atividade (segundos).
        'tempo_inatividade'   => 1800,
    ],

    'prazos' => [
        // Obrigações em aberto que vencem em até N dias são destacadas como
        // "próximas do vencimento". O Radar Contábil usará o mesmo valor.
        'janela_alerta_dias' => 7,
        // Documentos recebidos há mais de N dias sem análise são destacados.
        'analise_parada_dias' => 3,
    ],

    'uploads' => [
        // Fora do alcance direto da web (bloqueado por .htaccess);
        // os arquivos só são entregues pela rota autenticada documentos/arquivo.
        'diretorio'      => APP_ROOT . '/storage/uploads',
        // Limite da aplicação. O limite efetivo é o menor entre este valor e
        // upload_max_filesize / post_max_size do PHP.
        'tamanho_maximo' => 5 * 1024 * 1024,
    ],

    'auth' => [
        // Perfis que podem fazer login nesta versão.
        // Para ativar o portal do cliente (versão 2), incluir 'cliente'.
        'perfis_ativos' => ['admin', 'contador'],
        // Rota para onde usuários não autenticados são enviados.
        'rota_login'    => 'auth/login',
    ],
];
