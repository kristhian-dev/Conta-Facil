<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Layout das telas de autenticação (sem menu, conteúdo centralizado).
 *
 * @var string $conteudo
 * @var string|null $titulo
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' — ' . config('app.nome') : config('app.nome')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <?= $conteudo ?>
</body>
</html>
