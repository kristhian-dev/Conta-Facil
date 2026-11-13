<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Formulário de cadastro e edição de clientes.
 *
 * Os campos exclusivos de pessoa jurídica (nome fantasia e regime) são
 * ocultados pelo JavaScript quando o tipo é PF. Sem JavaScript, todos os
 * campos aparecem e o servidor ignora os que não se aplicam ao tipo.
 *
 * @var array|null $cliente   null no cadastro
 * @var array<string, string> $valores
 * @var array<string, string> $erros
 * @var array<int, array{id: int, nome: string}> $responsaveis
 */
use App\Models\Cliente;

$edicao = $cliente !== null;
$acao   = $edicao ? url('clientes/editar', ['id' => $cliente['id']]) : url('clientes/novo');
$voltar = $edicao ? url('clientes/ver', ['id' => $cliente['id']]) : url('clientes');
$v      = static fn (string $campo): string => $valores[$campo] ?? '';
$pf     = $v('tipo_pessoa') === 'PF';
$classe = static fn (string $campo): string => 'campo' . (isset($erros[$campo]) ? ' campo-invalido' : '');
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha"><a href="<?= e(url('clientes')) ?>">Clientes</a> /</p>
        <h1><?= $edicao ? 'Editar cliente' : 'Novo cliente' ?></h1>
    </div>
</div>

<?php if ($erros !== []): ?>
    <p class="alerta alerta-erro" role="alert">
        Não foi possível salvar. Corrija <?= count($erros) === 1 ? 'o campo destacado' : 'os campos destacados' ?> abaixo.
    </p>
<?php endif; ?>

<?php /* novalidate: a validação é feita no servidor, com erro por campo. */ ?>
<form class="cartao formulario" method="post" action="<?= e($acao) ?>" novalidate data-form-cliente>
    <?= csrf_campo() ?>

    <fieldset class="<?= e($classe('tipo_pessoa')) ?> campo-largo">
        <legend>Tipo de pessoa</legend>
        <div class="opcoes-radio">
            <?php foreach (Cliente::TIPOS as $tipo => $rotulo): ?>
                <label class="opcao-radio">
                    <input type="radio" name="tipo_pessoa" value="<?= e($tipo) ?>"
                        <?= $v('tipo_pessoa') === $tipo ? 'checked' : '' ?><?= atributos_erro($erros, 'tipo_pessoa') ?>>
                    <?= e($rotulo) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= erro_campo($erros, 'tipo_pessoa') ?>
    </fieldset>

    <div class="formulario-grade">
        <div class="<?= e($classe('razao_social')) ?> campo-largo">
            <label for="razao_social" data-rotulo-pf="Nome completo" data-rotulo-pj="Razão social">
                <?= $pf ? 'Nome completo' : 'Razão social' ?>
            </label>
            <input type="text" id="razao_social" name="razao_social" value="<?= e($v('razao_social')) ?>"
                   maxlength="160" required<?= atributos_erro($erros, 'razao_social') ?>>
            <?= erro_campo($erros, 'razao_social') ?>
        </div>

        <div class="<?= e($classe('cpf_cnpj')) ?>">
            <label for="cpf_cnpj" data-rotulo-pf="CPF" data-rotulo-pj="CNPJ"><?= $pf ? 'CPF' : 'CNPJ' ?></label>
            <input type="text" id="cpf_cnpj" name="cpf_cnpj" value="<?= e(formatar_cpf_cnpj($v('cpf_cnpj'))) ?>"
                   inputmode="numeric" maxlength="18" required data-mascara="cpf-cnpj"
                   <?= atributos_erro($erros, 'cpf_cnpj') ?>>
            <?= erro_campo($erros, 'cpf_cnpj') ?>
        </div>

        <div class="<?= e($classe('nome_fantasia')) ?>" data-somente="PJ">
            <label for="nome_fantasia">Nome fantasia <span class="texto-suave">(opcional)</span></label>
            <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?= e($v('nome_fantasia')) ?>"
                   maxlength="160"<?= atributos_erro($erros, 'nome_fantasia') ?>>
            <?= erro_campo($erros, 'nome_fantasia') ?>
        </div>

        <div class="<?= e($classe('regime_tributario')) ?>" data-somente="PJ">
            <label for="regime_tributario">Regime tributário</label>
            <select id="regime_tributario" name="regime_tributario"<?= atributos_erro($erros, 'regime_tributario') ?>>
                <option value="">Selecione…</option>
                <?= opcoes(array_combine(Cliente::REGIMES_PJ, Cliente::REGIMES_PJ), $v('regime_tributario')) ?>
            </select>
            <?= erro_campo($erros, 'regime_tributario') ?>
        </div>

        <div class="<?= e($classe('email')) ?>">
            <label for="email">E-mail <span class="texto-suave">(opcional)</span></label>
            <input type="email" id="email" name="email" value="<?= e($v('email')) ?>"
                   maxlength="160" autocomplete="off"<?= atributos_erro($erros, 'email') ?>>
            <?= erro_campo($erros, 'email') ?>
        </div>

        <div class="<?= e($classe('telefone')) ?>">
            <label for="telefone">Telefone <span class="texto-suave">(opcional)</span></label>
            <input type="tel" id="telefone" name="telefone" value="<?= e(formatar_telefone($v('telefone'))) ?>"
                   maxlength="20" placeholder="(86) 99999-0000" data-mascara="telefone"
                   <?= atributos_erro($erros, 'telefone') ?>>
            <?= erro_campo($erros, 'telefone') ?>
        </div>

        <div class="<?= e($classe('responsavel_id')) ?>">
            <label for="responsavel_id">Responsável</label>
            <select id="responsavel_id" name="responsavel_id"<?= atributos_erro($erros, 'responsavel_id') ?>>
                <option value="">Selecione…</option>
                <?= opcoes(array_column($responsaveis, 'nome', 'id'), $v('responsavel_id')) ?>
            </select>
            <?= erro_campo($erros, 'responsavel_id') ?>
        </div>

        <div class="<?= e($classe('status')) ?>">
            <label for="status">Situação</label>
            <select id="status" name="status"<?= atributos_erro($erros, 'status') ?>>
                <?= opcoes(Cliente::STATUS, $v('status')) ?>
            </select>
            <?= erro_campo($erros, 'status') ?>
        </div>

        <div class="<?= e($classe('observacoes')) ?> campo-largo">
            <label for="observacoes">Observações <span class="texto-suave">(opcional)</span></label>
            <textarea id="observacoes" name="observacoes" rows="4" maxlength="2000"
                <?= atributos_erro($erros, 'observacoes') ?>><?= e($v('observacoes')) ?></textarea>
            <?= erro_campo($erros, 'observacoes') ?>
        </div>
    </div>

    <div class="formulario-acoes">
        <button type="submit" class="botao botao-primario"><?= $edicao ? 'Salvar alterações' : 'Cadastrar cliente' ?></button>
        <a class="botao botao-secundario" href="<?= e($voltar) ?>">Cancelar</a>
    </div>
</form>
