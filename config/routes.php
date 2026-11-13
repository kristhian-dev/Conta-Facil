<?php

declare(strict_types=1);

/**
 * Tabela de rotas (lista do que é permitido).
 *
 * URL: index.php?r=<rota>  (ex.: index.php?r=clientes/editar&id=5)
 *
 * Formato:
 *   'rota' => [
 *       'metodos' => ['GET'] | ['POST'] | ['GET', 'POST'],
 *       'acao'    => [Controller::class, 'metodo'],
 *       'acesso'  => 'publico' | 'autenticado' | ['admin', 'contador', ...],
 *   ]
 *
 * - 'publico'     : qualquer visitante.
 * - 'autenticado' : qualquer usuário logado.
 * - [perfis]      : somente os perfis listados (os demais recebem 403).
 *
 * Toda requisição POST tem o token CSRF validado pelo Router.
 * Rotas do futuro portal do cliente usarão o prefixo 'portal/'.
 */

defined('APP_ROOT') || exit;

use App\Controllers\AuthController;
use App\Controllers\ClienteController;
use App\Controllers\DocumentoController;
use App\Controllers\DashboardController;
use App\Controllers\ObrigacaoController;
use App\Controllers\RadarController;

// Perfis com acesso às telas de gestão do escritório.
$equipe = ['admin', 'contador'];

return [
    // Autenticação
    'auth/login' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [AuthController::class, 'login'],
        'acesso'  => 'publico',
    ],
    'auth/logout' => [
        'metodos' => ['POST'],
        'acao'    => [AuthController::class, 'logout'],
        'acesso'  => 'autenticado',
    ],

    // Página inicial: painel (dashboard). Somente leitura.
    'home' => [
        'metodos' => ['GET'],
        'acao'    => [DashboardController::class, 'index'],
        'acesso'  => $equipe,
    ],

    // Radar Contábil (somente leitura)
    'radar' => [
        'metodos' => ['GET'],
        'acao'    => [RadarController::class, 'index'],
        'acesso'  => $equipe,
    ],

    // Clientes
    'clientes' => [
        'metodos' => ['GET'],
        'acao'    => [ClienteController::class, 'index'],
        'acesso'  => $equipe,
    ],
    'clientes/ver' => [
        'metodos' => ['GET'],
        'acao'    => [ClienteController::class, 'ver'],
        'acesso'  => $equipe,
    ],
    'clientes/novo' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [ClienteController::class, 'novo'],
        'acesso'  => $equipe,
    ],
    'clientes/editar' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [ClienteController::class, 'editar'],
        'acesso'  => $equipe,
    ],
    'clientes/excluir' => [
        'metodos' => ['POST'],
        'acao'    => [ClienteController::class, 'excluir'],
        'acesso'  => ['admin'],
    ],

    // Obrigações
    'obrigacoes' => [
        'metodos' => ['GET'],
        'acao'    => [ObrigacaoController::class, 'index'],
        'acesso'  => $equipe,
    ],
    'obrigacoes/ver' => [
        'metodos' => ['GET'],
        'acao'    => [ObrigacaoController::class, 'ver'],
        'acesso'  => $equipe,
    ],
    'obrigacoes/nova' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [ObrigacaoController::class, 'nova'],
        'acesso'  => $equipe,
    ],
    'obrigacoes/editar' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [ObrigacaoController::class, 'editar'],
        'acesso'  => $equipe,
    ],
    'obrigacoes/concluir' => [
        'metodos' => ['POST'],
        'acao'    => [ObrigacaoController::class, 'concluir'],
        'acesso'  => $equipe,
    ],
    'obrigacoes/cancelar' => [
        'metodos' => ['POST'],
        'acao'    => [ObrigacaoController::class, 'cancelar'],
        'acesso'  => $equipe,
    ],
    'obrigacoes/excluir' => [
        'metodos' => ['POST'],
        'acao'    => [ObrigacaoController::class, 'excluir'],
        'acesso'  => ['admin'],
    ],

    // Documentos
    'documentos' => [
        'metodos' => ['GET'],
        'acao'    => [DocumentoController::class, 'index'],
        'acesso'  => $equipe,
    ],
    'documentos/ver' => [
        'metodos' => ['GET'],
        'acao'    => [DocumentoController::class, 'ver'],
        'acesso'  => $equipe,
    ],
    'documentos/novo' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [DocumentoController::class, 'novo'],
        'acesso'  => $equipe,
    ],
    'documentos/editar' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [DocumentoController::class, 'editar'],
        'acesso'  => $equipe,
    ],
    'documentos/receber' => [
        'metodos' => ['GET', 'POST'],
        'acao'    => [DocumentoController::class, 'receber'],
        'acesso'  => $equipe,
    ],
    'documentos/aprovar' => [
        'metodos' => ['POST'],
        'acao'    => [DocumentoController::class, 'aprovar'],
        'acesso'  => $equipe,
    ],
    'documentos/rejeitar' => [
        'metodos' => ['POST'],
        'acao'    => [DocumentoController::class, 'rejeitar'],
        'acesso'  => $equipe,
    ],
    // Única forma de acessar os arquivos enviados (storage/ é bloqueado na web).
    'documentos/arquivo' => [
        'metodos' => ['GET'],
        'acao'    => [DocumentoController::class, 'arquivo'],
        'acesso'  => $equipe,
    ],
    'documentos/excluir' => [
        'metodos' => ['POST'],
        'acao'    => [DocumentoController::class, 'excluir'],
        'acesso'  => ['admin'],
    ],
];
