<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Página de erro genérica (403, 404, 405, 500).
 *
 * @var int $status
 * @var string $mensagem
 */
?>
<section class="erro">
    <h1>Erro <?= e($status) ?></h1>
    <p><?= e($mensagem) ?></p>
    <p><a href="<?= e(url(config('app.rota_inicial'))) ?>">Voltar ao início</a></p>
</section>
