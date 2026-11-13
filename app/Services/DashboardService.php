<?php

declare(strict_types=1);

namespace App\Services;

defined('APP_ROOT') || exit;

use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Obrigacao;

/**
 * Dados do painel inicial (dashboard).
 *
 * Não contém regras próprias: cada número vem de um método já usado pelos
 * módulos (mesmas condições de situação e prazo), e o risco vem do RadarService.
 *
 * Consultas por carregamento (fixas, sem N+1): 8
 *   1 clientes por situação            Cliente::contarPorStatus()
 *   1 indicadores de obrigações         Obrigacao::contarIndicadores()
 *   1 indicadores de documentos         Documento::contarPendencias()
 *   3 Radar (obrigações, documentos, clientes ativos)   RadarService::analisar()
 *   1 lista de obrigações em aberto (LIMIT)              Obrigacao::listar()
 *   1 lista de documentos pendentes (LIMIT)              Documento::listar()
 *
 * $clienteId: preparação para o portal (versão 2) — limita tudo a um cliente.
 */
final class DashboardService
{
    /** Itens nas listas resumidas (o restante fica a um clique, nos módulos). */
    public const LIMITE_LISTAS = 6;
    /** Clientes do Radar exibidos no painel. */
    public const LIMITE_RADAR = 5;

    private Cliente $clientes;
    private Obrigacao $obrigacoes;
    private Documento $documentos;
    private RadarService $radar;

    public function __construct(
        ?Cliente $clientes = null,
        ?Obrigacao $obrigacoes = null,
        ?Documento $documentos = null,
        ?RadarService $radar = null
    ) {
        $this->clientes   = $clientes ?? new Cliente();
        $this->obrigacoes = $obrigacoes ?? new Obrigacao();
        $this->documentos = $documentos ?? new Documento();
        $this->radar      = $radar ?? new RadarService($this->obrigacoes, $this->documentos, $this->clientes);
    }

    public function montar(?int $clienteId = null): array
    {
        $radar = $this->radar->analisar($clienteId);

        return [
            'clientes'   => $this->clientes->contarPorStatus($clienteId),
            'obrigacoes' => $this->obrigacoes->contarIndicadores($clienteId),
            'documentos' => $this->documentos->contarPendencias($clienteId),
            'radar'      => [
                'resumo'   => $radar['resumo'],
                // O RadarService já entrega os clientes do mais crítico ao menos crítico.
                'clientes' => array_slice($radar['clientes'], 0, self::LIMITE_RADAR),
                'total_clientes' => count($radar['clientes']),
            ],
            // Em aberto, do vencimento mais antigo para o mais distante (atrasadas primeiro).
            'proximas_obrigacoes' => $this->obrigacoes->listar(
                ['prazo' => 'em_aberto', 'ordenar' => 'vencimento'],
                self::LIMITE_LISTAS,
                0,
                $clienteId
            ),
            // Solicitados, rejeitados (reenvio) e recebidos, na ordem de prioridade do módulo.
            'documentos_pendentes' => $this->documentos->listar(
                ['pendencia' => 'aguardando', 'ordenar' => 'prioridade'],
                self::LIMITE_LISTAS,
                0,
                $clienteId
            ),
            'janela' => Obrigacao::janela(),
        ];
    }
}
