<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Mensagens flash (exibidas uma única vez após um redirecionamento).
 * Tipos: sucesso, erro, aviso.
 */
foreach (App\Core\Session::consumirFlash() as $mensagem): ?>
    <p class="alerta alerta-<?= e($mensagem['tipo']) ?>" role="status"><?= e($mensagem['mensagem']) ?></p>
<?php endforeach; ?>
