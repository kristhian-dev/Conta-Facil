<?php

declare(strict_types=1);

namespace App\Controllers;

defined('APP_ROOT') || exit;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cliente;
use App\Models\Usuario;
use App\Services\RadarService;

/**
 * Radar Contábil (somente leitura: não altera dados, por isso não há POST/CSRF).
 */
final class RadarController extends Controller
{
    /**
     * GET radar — resumo, clientes que precisam de atenção e detalhamento.
     */
    public function index(): void
    {
        $filtros  = $this->filtros();
        $restrito = Auth::clienteRestrito();

        // No futuro portal, o escopo do usuário prevalece sobre qualquer filtro da URL.
        $clienteId = $restrito ?? ($filtros['cliente_id'] > 0 ? $filtros['cliente_id'] : null);

        $radar = (new RadarService())->analisar(
            $clienteId,
            $filtros['responsavel_id'] > 0 ? $filtros['responsavel_id'] : null
        );

        $this->view('radar/index', [
            'titulo'       => 'Radar Contábil',
            'radar'        => $radar,
            'clientes'     => RadarService::filtrar($radar['clientes'], $filtros['risco'], $filtros['tipo']),
            'filtros'      => $filtros,
            'opcoesClientes' => (new Cliente())->listarParaSelecao(true, null, $restrito),
            'responsaveis' => (new Usuario())->listarResponsaveis(),
        ]);
    }

    /**
     * Filtros da tela, aceitando somente valores conhecidos.
     */
    private function filtros(): array
    {
        $opcao   = static fn (string $v, array $permitidos): string => in_array($v, $permitidos, true) ? $v : '';
        $inteiro = static fn (string $v): int => ($i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false ? 0 : $i;

        return [
            'cliente_id'     => $inteiro($this->query('cliente_id')),
            'responsavel_id' => $inteiro($this->query('responsavel_id')),
            'risco'          => $opcao($this->query('risco'), array_keys(RadarService::RISCOS)),
            'tipo'           => $opcao($this->query('tipo'), array_keys(RadarService::TIPOS)),
        ];
    }
}
