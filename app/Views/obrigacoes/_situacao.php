<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Etiquetas de uma obrigação: situação gravada (status) + prazo calculado.
 * As duas informações aparecem separadas para não confundir o que está
 * no banco com o que é derivado da data de hoje.
 *
 * @var array $obrigacao
 */
use App\Models\Obrigacao;

$prazo = Obrigacao::prazo($obrigacao);
$dias  = (int) $obrigacao['dias_para_vencer'];
?>
<span class="etiqueta etiqueta-<?= e($obrigacao['status']) ?>"><?= e(Obrigacao::STATUS[$obrigacao['status']]) ?></span>
<?php if ($prazo === 'atrasada'): ?>
    <span class="etiqueta etiqueta-prazo-atrasada">Atrasada há <?= e(plural(-$dias, 'dia', 'dias')) ?></span>
<?php elseif ($prazo === 'vence_hoje'): ?>
    <span class="etiqueta etiqueta-prazo-hoje">Vence hoje</span>
<?php elseif ($prazo === 'proxima'): ?>
    <span class="etiqueta etiqueta-prazo-proxima">Vence em <?= e(plural($dias, 'dia', 'dias')) ?></span>
<?php elseif ($obrigacao['status'] === 'concluida' && $obrigacao['data_conclusao'] > $obrigacao['data_vencimento']): ?>
    <span class="etiqueta etiqueta-neutra">Concluída após o vencimento</span>
<?php endif; ?>
