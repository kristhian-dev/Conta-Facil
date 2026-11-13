<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Tela de login.
 *
 * @var string $email           E-mail digitado (para repovoar o campo)
 * @var array<string,string> $erros  Erros de validação por campo
 * @var string|null $erroGeral  Falha de autenticação (mensagem genérica)
 */
?>
<main class="login">
    <section class="login-cartao" aria-labelledby="login-titulo">
        <header class="login-cabecalho">
            <div class="marca">
                <span class="marca-logo" aria-hidden="true">CF</span>
                <span id="login-titulo"><?= e(config('app.nome')) ?></span>
            </div>
            <p>Acesse sua conta para continuar</p>
        </header>

        <?= App\Core\View::renderizar('partials/flash') ?>

        <?php if ($erroGeral !== null): ?>
            <p class="alerta alerta-erro" role="alert"><?= e($erroGeral) ?></p>
        <?php endif; ?>

        <?php /* novalidate: a validação é feita no servidor, com erro por campo. */ ?>
        <form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
            <?= csrf_campo() ?>

            <div class="campo<?= isset($erros['email']) ? ' campo-invalido' : '' ?>">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       maxlength="160" autocomplete="username" autofocus required
                       <?= isset($erros['email']) ? 'aria-invalid="true" aria-describedby="email-erro"' : '' ?>>
                <?php if (isset($erros['email'])): ?>
                    <p class="campo-erro" id="email-erro"><?= e($erros['email']) ?></p>
                <?php endif; ?>
            </div>

            <div class="campo<?= isset($erros['senha']) ? ' campo-invalido' : '' ?>">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha"
                       maxlength="255" autocomplete="current-password" required
                       <?= isset($erros['senha']) ? 'aria-invalid="true" aria-describedby="senha-erro"' : '' ?>>
                <?php if (isset($erros['senha'])): ?>
                    <p class="campo-erro" id="senha-erro"><?= e($erros['senha']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="botao botao-primario botao-bloco">Entrar</button>
        </form>
    </section>
</main>
