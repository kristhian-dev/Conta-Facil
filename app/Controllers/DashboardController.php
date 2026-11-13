<?php

declare(strict_types=1);

namespace App\Controllers;

defined('APP_ROOT') || exit;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DashboardService;

/**
 * Painel inicial (rota "home"). Somente leitura.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard/index', [
            'titulo'  => 'Painel',
            'usuario' => Auth::usuario(),
            'painel'  => (new DashboardService())->montar(Auth::clienteRestrito()),
        ]);
    }
}
