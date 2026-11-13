<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Etiquetas de um documento: situação gravada (sólida) + pendência
 * calculada (tracejada), para não confundir as duas informações.
 *
 * @var array $documento
 */
use App\Models\Documento;

$d         = $documento;
$pendencia = Documento::pendencia($d);
?>
<span class="etiqueta etiqueta-<?= e($d['status']) ?>"><?= e(Documento::STATUS[$d['status']]) ?></span>
<?php if ($d['status'] === 'solicitado'): ?>
    <span class="etiqueta etiqueta-prazo-proxima">Aguardando recebimento</span>
<?php elseif ($d['status'] === 'rejeitado'): ?>
    <span class="etiqueta etiqueta-prazo-atrasada">Aguardando reenvio</span>
<?php elseif ($d['status'] === 'recebido'): ?>
    <?php if ($pendencia !== null): ?>
        <span class="etiqueta etiqueta-prazo-hoje">Aguardando análise há <?= e(plural($pendencia['dias'], 'dia', 'dias')) ?></span>
    <?php else: ?>
        <span class="etiqueta etiqueta-prazo-info">Aguardando análise</span>
    <?php endif; ?>
<?php endif; ?>
<?php if ($pendencia !== null && $pendencia['tipo'] === 'prazo_vencido'): ?>
    <span class="etiqueta etiqueta-prazo-atrasada">Prazo vencido há <?= e(plural($pendencia['dias'], 'dia', 'dias')) ?></span>
<?php endif; ?>
