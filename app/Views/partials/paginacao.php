<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Navegação entre páginas, preservando os filtros na URL.
 *
 * @var App\Core\Paginator $paginacao
 * @var string $rota
 * @var array<string, scalar> $parametros filtros ativos
 */
$link = static fn (int $pagina): string => url($rota, $parametros + ['pagina' => $pagina]);
?>
<nav class="paginacao" aria-label="Paginação">
    <p class="paginacao-resumo">
        Mostrando <?= e($paginacao->primeiroItem()) ?>–<?= e($paginacao->ultimoItem()) ?>
        de <?= e($paginacao->total) ?>
    </p>

    <?php if ($paginacao->totalPaginas > 1): ?>
        <ul class="paginacao-lista">
            <li>
                <?php if ($paginacao->pagina > 1): ?>
                    <a href="<?= e($link($paginacao->pagina - 1)) ?>" rel="prev">‹ Anterior</a>
                <?php else: ?>
                    <span aria-disabled="true">‹ Anterior</span>
                <?php endif; ?>
            </li>

            <?php foreach ($paginacao->paginasVisiveis() as $pagina): ?>
                <li>
                    <?php if ($pagina === null): ?>
                        <span class="paginacao-reticencias">…</span>
                    <?php elseif ($pagina === $paginacao->pagina): ?>
                        <span aria-current="page"><?= e($pagina) ?></span>
                    <?php else: ?>
                        <a href="<?= e($link($pagina)) ?>" aria-label="Página <?= e($pagina) ?>"><?= e($pagina) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>

            <li>
                <?php if ($paginacao->pagina < $paginacao->totalPaginas): ?>
                    <a href="<?= e($link($paginacao->pagina + 1)) ?>" rel="next">Próxima ›</a>
                <?php else: ?>
                    <span aria-disabled="true">Próxima ›</span>
                <?php endif; ?>
            </li>
        </ul>
    <?php endif; ?>
</nav>
