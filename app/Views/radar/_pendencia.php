<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Uma pendência do Radar (item de lista).
 *
 * @var array $p pendência gerada pelo RadarService
 */
$rotuloTipo = [
    'obrigacao_atrasada'    => 'Obrigação atrasada',
    'obrigacao_proxima'     => 'Obrigação a vencer',
    'documento_recebimento' => 'Documento não enviado',
    'documento_analise'     => 'Documento para analisar',
][$p['tipo']];

$rotuloGravidade = ['alta' => 'Gravidade alta', 'media' => 'Gravidade média', 'baixa' => 'Gravidade baixa'][$p['gravidade']];
$data = formatar_data($p['data']);
$dias = static fn (int $n): string => plural($n, 'dia', 'dias');

$detalhe = match ($p['motivo']) {
    'atrasada'        => 'Atrasada há ' . $dias($p['dias']) . " · venceu em {$data}",
    'vence_hoje'      => "Vence hoje ({$data})",
    'vence_em'        => 'Vence em ' . $dias($p['dias']) . " · {$data}",
    'prazo_vencido'   => 'Prazo de envio vencido há ' . $dias($p['dias']) . " ({$data})",
    'no_prazo'        => $p['dias'] === 0 ? "Prazo de envio termina hoje ({$data})" : "Prazo de envio: {$data} (em " . $dias($p['dias']) . ')',
    'sem_prazo'       => 'Sem prazo de envio definido',
    'analise_parada'  => 'Aguardando análise há ' . $dias($p['dias']) . " (recebido em {$data})",
    'analise_recente' => "Recebido em {$data} · aguardando análise",
    default           => '',
};
if ($p['origem'] === 'documento' && $p['status'] === 'rejeitado') {
    $detalhe = 'Reenvio pedido após rejeição · ' . $detalhe;
}

$link = url($p['origem'] === 'obrigacao' ? 'obrigacoes/ver' : 'documentos/ver', ['id' => $p['id']]);
?>
<li class="radar-pendencia gravidade-<?= e($p['gravidade']) ?>">
    <span class="gravidade-marcador" title="<?= e($rotuloGravidade) ?>"><span class="sr-only"><?= e($rotuloGravidade) ?></span></span>
    <div class="radar-pendencia-texto">
        <span class="radar-pendencia-tipo"><?= e($rotuloTipo) ?></span>
        <a href="<?= e($link) ?>"><?= e($p['titulo']) ?></a>
        <span class="radar-pendencia-detalhe"><?= e($detalhe) ?></span>
        <?php if (($p['obrigacao_id'] ?? null) !== null): ?>
            <span class="radar-pendencia-vinculo">
                Obrigação: <a href="<?= e(url('obrigacoes/ver', ['id' => $p['obrigacao_id']])) ?>"><?= e($p['obrigacao_titulo']) ?></a>
            </span>
        <?php endif; ?>
    </div>
</li>
