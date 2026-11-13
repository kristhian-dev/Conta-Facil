<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Solicitação (novo) e edição dos dados descritivos de um documento.
 * Na edição, cliente e obrigação aparecem apenas para consulta.
 *
 * @var array|null $documento
 * @var array<string, string> $valores
 * @var array<string, string> $erros
 * @var array $clientes     (somente na solicitação)
 * @var array $obrigacoes   (somente na solicitação)
 */
$edicao = $documento !== null;
$acao   = $edicao ? url('documentos/editar', ['id' => $documento['id']]) : url('documentos/novo');
$voltar = $edicao ? url('documentos/ver', ['id' => $documento['id']]) : url('documentos');
$v      = static fn (string $campo): string => $valores[$campo] ?? '';
$classe = static fn (string $campo): string => 'campo' . (isset($erros[$campo]) ? ' campo-invalido' : '');
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha"><a href="<?= e(url('documentos')) ?>">Documentos</a> /</p>
        <h1><?= $edicao ? 'Editar documento' : 'Solicitar documento' ?></h1>
    </div>
</div>

<?php if ($erros !== []): ?>
    <p class="alerta alerta-erro" role="alert">
        Não foi possível salvar. Corrija <?= count($erros) === 1 ? 'o campo destacado' : 'os campos destacados' ?> abaixo.
    </p>
<?php endif; ?>

<form class="cartao formulario" method="post" action="<?= e($acao) ?>" novalidate>
    <?= csrf_campo() ?>

    <div class="formulario-grade">
        <?php if ($edicao): ?>
            <div class="campo">
                <label for="cliente_fixo">Cliente</label>
                <input type="text" id="cliente_fixo" value="<?= e($documento['cliente_nome']) ?>" readonly>
            </div>
            <div class="campo">
                <label for="obrigacao_fixa">Obrigação</label>
                <input type="text" id="obrigacao_fixa" readonly value="<?= e($documento['obrigacao_id'] !== null
                    ? $documento['tipo_nome'] . ' ' . formatar_competencia($documento['obrigacao_competencia'])
                    : 'Sem vínculo com obrigação') ?>">
            </div>
            <p class="campo-ajuda campo-largo">Cliente e obrigação não podem ser alterados depois da solicitação.</p>
        <?php else: ?>
            <div class="<?= e($classe('cliente_id')) ?>">
                <label for="cliente_id">Cliente</label>
                <select id="cliente_id" name="cliente_id"<?= atributos_erro($erros, 'cliente_id') ?>>
                    <option value="">Selecione…</option>
                    <?= opcoes(array_column($clientes, 'razao_social', 'id'), $v('cliente_id')) ?>
                </select>
                <?= erro_campo($erros, 'cliente_id') ?>
            </div>

            <div class="<?= e($classe('obrigacao_id')) ?>">
                <label for="obrigacao_id">Obrigação <span class="texto-suave">(opcional)</span></label>
                <?php /* O JavaScript mostra só as obrigações do cliente escolhido; o servidor valida o vínculo. */ ?>
                <select id="obrigacao_id" name="obrigacao_id" data-filtrar-por="cliente_id"<?= atributos_erro($erros, 'obrigacao_id') ?>>
                    <option value="">Sem vínculo com obrigação</option>
                    <?php foreach ($obrigacoes as $o): ?>
                        <option value="<?= e($o['id']) ?>" data-cliente="<?= e($o['cliente_id']) ?>"
                            <?= (string) $o['id'] === $v('obrigacao_id') ? 'selected' : '' ?>>
                            <?= e($o['tipo_nome'] . ' ' . formatar_competencia($o['competencia']) . ' — vence ' . formatar_data($o['data_vencimento'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= erro_campo($erros, 'obrigacao_id') ?>
            </div>
        <?php endif; ?>

        <div class="<?= e($classe('titulo')) ?> campo-largo">
            <label for="titulo">Documento solicitado</label>
            <input type="text" id="titulo" name="titulo" value="<?= e($v('titulo')) ?>" maxlength="160"
                   placeholder="Ex.: Extrato bancário de outubro"<?= atributos_erro($erros, 'titulo') ?>>
            <?= erro_campo($erros, 'titulo') ?>
        </div>

        <div class="<?= e($classe('prazo_envio')) ?>">
            <label for="prazo_envio">Prazo para envio <span class="texto-suave">(opcional)</span></label>
            <input type="date" id="prazo_envio" name="prazo_envio" value="<?= e($v('prazo_envio')) ?>"
                   <?= atributos_erro($erros, 'prazo_envio') ?>>
            <?= erro_campo($erros, 'prazo_envio') ?>
        </div>

        <div class="<?= e($classe('descricao')) ?> campo-largo">
            <label for="descricao">Orientações ao cliente <span class="texto-suave">(opcional)</span></label>
            <textarea id="descricao" name="descricao" rows="3" maxlength="2000"
                <?= atributos_erro($erros, 'descricao') ?>><?= e($v('descricao')) ?></textarea>
            <?= erro_campo($erros, 'descricao') ?>
        </div>
    </div>

    <div class="formulario-acoes">
        <button type="submit" class="botao botao-primario"><?= $edicao ? 'Salvar alterações' : 'Solicitar documento' ?></button>
        <a class="botao botao-secundario" href="<?= e($voltar) ?>">Cancelar</a>
    </div>
</form>
