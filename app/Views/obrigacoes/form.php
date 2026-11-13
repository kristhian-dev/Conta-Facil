<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Formulário de cadastro e edição de obrigações.
 *
 * @var array|null $obrigacao  null no cadastro
 * @var array<string, string> $valores
 * @var array<string, string> $erros
 * @var array $clientes
 * @var array $tipos
 * @var array $responsaveis
 * @var int $documentos       documentos vinculados (edição)
 */
use App\Models\Obrigacao;

$edicao = $obrigacao !== null;
$acao   = $edicao ? url('obrigacoes/editar', ['id' => $obrigacao['id']]) : url('obrigacoes/nova');
$voltar = $edicao ? url('obrigacoes/ver', ['id' => $obrigacao['id']]) : url('obrigacoes');
$v      = static fn (string $campo): string => $valores[$campo] ?? '';
$classe = static fn (string $campo): string => 'campo' . (isset($erros[$campo]) ? ' campo-invalido' : '');

$opcoesClientes = [];
foreach ($clientes as $c) {
    $opcoesClientes[$c['id']] = $c['razao_social'] . ' — ' . formatar_cpf_cnpj($c['cpf_cnpj'])
        . ($c['status'] === 'inativo' ? ' (inativo)' : '');
}
// Com documentos vinculados, o cliente fica fixo (o banco também impede a troca).
$clienteFixo = $edicao && $documentos > 0;
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha"><a href="<?= e(url('obrigacoes')) ?>">Obrigações</a> /</p>
        <h1><?= $edicao ? 'Editar obrigação' : 'Nova obrigação' ?></h1>
    </div>
</div>

<?php if ($erros !== []): ?>
    <p class="alerta alerta-erro" role="alert">
        Não foi possível salvar. Corrija <?= count($erros) === 1 ? 'o campo destacado' : 'os campos destacados' ?> abaixo.
    </p>
<?php endif; ?>

<?php /* novalidate: a validação é feita no servidor, com erro por campo. */ ?>
<form class="cartao formulario" method="post" action="<?= e($acao) ?>" novalidate>
    <?= csrf_campo() ?>

    <div class="formulario-grade">
        <div class="<?= e($classe('cliente_id')) ?> campo-largo">
            <label for="cliente_id">Cliente</label>
            <?php if ($clienteFixo): ?>
                <input type="hidden" name="cliente_id" value="<?= e($obrigacao['cliente_id']) ?>">
                <input type="text" id="cliente_id" value="<?= e($obrigacao['cliente_nome']) ?>" readonly>
                <p class="campo-ajuda">
                    O cliente não pode ser alterado: há <?= e(plural($documentos, 'documento vinculado', 'documentos vinculados')) ?> a esta obrigação.
                </p>
            <?php else: ?>
                <select id="cliente_id" name="cliente_id"<?= atributos_erro($erros, 'cliente_id') ?>>
                    <option value="">Selecione…</option>
                    <?= opcoes($opcoesClientes, $v('cliente_id')) ?>
                </select>
            <?php endif; ?>
            <?= erro_campo($erros, 'cliente_id') ?>
        </div>

        <div class="<?= e($classe('tipo_obrigacao_id')) ?>">
            <label for="tipo_obrigacao_id">Tipo de obrigação</label>
            <select id="tipo_obrigacao_id" name="tipo_obrigacao_id"<?= atributos_erro($erros, 'tipo_obrigacao_id') ?>>
                <option value="">Selecione…</option>
                <?= opcoes(array_column($tipos, 'nome', 'id'), $v('tipo_obrigacao_id')) ?>
            </select>
            <?= erro_campo($erros, 'tipo_obrigacao_id') ?>
        </div>

        <div class="<?= e($classe('competencia')) ?>">
            <label for="competencia">Competência</label>
            <input type="month" id="competencia" name="competencia" value="<?= e($v('competencia')) ?>"
                   placeholder="AAAA-MM"<?= atributos_erro($erros, 'competencia') ?>>
            <?= erro_campo($erros, 'competencia') ?>
        </div>

        <div class="<?= e($classe('data_vencimento')) ?>">
            <label for="data_vencimento">Vencimento</label>
            <input type="date" id="data_vencimento" name="data_vencimento" value="<?= e($v('data_vencimento')) ?>"
                   <?= atributos_erro($erros, 'data_vencimento') ?>>
            <?= erro_campo($erros, 'data_vencimento') ?>
        </div>

        <div class="<?= e($classe('valor')) ?>">
            <label for="valor">Valor (R$) <span class="texto-suave">(opcional)</span></label>
            <input type="text" id="valor" name="valor" value="<?= e($v('valor')) ?>" inputmode="decimal"
                   maxlength="20" placeholder="0,00"<?= atributos_erro($erros, 'valor') ?>>
            <?= erro_campo($erros, 'valor') ?>
        </div>

        <div class="<?= e($classe('status')) ?>">
            <label for="status">Situação</label>
            <select id="status" name="status"<?= atributos_erro($erros, 'status') ?>>
                <?= opcoes(Obrigacao::STATUS, $v('status')) ?>
            </select>
            <?= erro_campo($erros, 'status') ?>
        </div>

        <?php /* Exibido pelo JavaScript só quando a situação é "Concluída"; o servidor valida em qualquer caso. */ ?>
        <div class="<?= e($classe('data_conclusao')) ?>" data-visivel-se="status=concluida">
            <label for="data_conclusao">Data de conclusão</label>
            <input type="date" id="data_conclusao" name="data_conclusao" value="<?= e($v('data_conclusao')) ?>"
                   max="<?= e(date('Y-m-d')) ?>"<?= atributos_erro($erros, 'data_conclusao') ?>>
            <?= erro_campo($erros, 'data_conclusao') ?>
        </div>

        <div class="<?= e($classe('responsavel_id')) ?>">
            <label for="responsavel_id">Responsável</label>
            <select id="responsavel_id" name="responsavel_id"<?= atributos_erro($erros, 'responsavel_id') ?>>
                <option value="">Selecione…</option>
                <?= opcoes(array_column($responsaveis, 'nome', 'id'), $v('responsavel_id')) ?>
            </select>
            <?= erro_campo($erros, 'responsavel_id') ?>
        </div>

        <div class="<?= e($classe('observacoes')) ?> campo-largo">
            <label for="observacoes">Observações <span class="texto-suave">(opcional)</span></label>
            <textarea id="observacoes" name="observacoes" rows="3" maxlength="2000"
                <?= atributos_erro($erros, 'observacoes') ?>><?= e($v('observacoes')) ?></textarea>
            <?= erro_campo($erros, 'observacoes') ?>
        </div>
    </div>

    <div class="formulario-acoes">
        <button type="submit" class="botao botao-primario"><?= $edicao ? 'Salvar alterações' : 'Cadastrar obrigação' ?></button>
        <a class="botao botao-secundario" href="<?= e($voltar) ?>">Cancelar</a>
    </div>
</form>
