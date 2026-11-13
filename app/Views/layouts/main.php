<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Layout principal com barra superior e menu.
 * Novos módulos são incluídos em $menu.
 *
 * @var string $conteudo HTML da view já renderizada
 * @var string|null $titulo
 */
$usuarioLogado = App\Core\Auth::usuario();
$rotaAtual     = rota_atual();
$menu = [
    config('app.rota_inicial') => 'Painel',
    'radar'                    => 'Radar',
    'clientes'                 => 'Clientes',
    'obrigacoes'               => 'Obrigações',
    'documentos'               => 'Documentos',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' — ' . config('app.nome') : config('app.nome')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
    <header class="topo">
        <a class="marca" href="<?= e(url(config('app.rota_inicial'))) ?>">
            <span class="marca-logo" aria-hidden="true">CF</span>
            <?= e(config('app.nome')) ?>
        </a>

        <?php if ($usuarioLogado !== null): ?>
            <nav class="topo-menu" aria-label="Principal">
                <?php foreach ($menu as $rota => $rotulo): ?>
                    <?php $ativo = $rotaAtual === $rota || str_starts_with($rotaAtual, $rota . '/'); ?>
                    <a href="<?= e(url($rota)) ?>"<?= $ativo ? ' aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="topo-usuario">
                <span><?= e($usuarioLogado['nome']) ?> (<?= e($usuarioLogado['perfil']) ?>)</span>
                <form method="post" action="<?= e(url('auth/logout')) ?>">
                    <?= csrf_campo() ?>
                    <button type="submit" class="botao botao-secundario">Sair</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <main class="conteudo">
        <?= App\Core\View::renderizar('partials/flash') ?>

        <?= $conteudo ?>
    </main>
</body>
</html>
