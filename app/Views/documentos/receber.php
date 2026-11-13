<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Registro de recebimento com envio do arquivo.
 *
 * @var array $documento
 * @var array<string, string> $valores
 * @var array<string, string> $erros
 * @var int $limite tamanho máximo efetivo em bytes
 */
use App\Core\Upload;
use App\Models\Documento;

$d      = $documento;
$v      = static fn (string $campo): string => $valores[$campo] ?? '';
$classe = static fn (string $campo): string => 'campo' . (isset($erros[$campo]) ? ' campo-invalido' : '');
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha">
            <a href="<?= e(url('documentos')) ?>">Documentos</a> /
            <a href="<?= e(url('documentos/ver', ['id' => $d['id']])) ?>"><?= e($d['titulo']) ?></a> /
        </p>
        <h1>Registrar recebimento</h1>
        <p class="texto-suave"><?= e($d['cliente_nome']) ?><?= $d['obrigacao_id'] !== null
            ? ' · ' . e($d['tipo_nome'] . ' ' . formatar_competencia($d['obrigacao_competencia'])) : '' ?></p>
    </div>
</div>

<?php if ($d['status'] === 'rejeitado'): ?>
    <div class="alerta alerta-aviso" role="status">
        <strong>Reenvio após rejeição.</strong> Motivo: <?= e($d['motivo_rejeicao']) ?><br>
        O novo arquivo substituirá o anterior (<?= e($d['arquivo_original']) ?>).
    </div>
<?php endif; ?>

<?php if ($erros !== []): ?>
    <p class="alerta alerta-erro" role="alert">
        Não foi possível registrar o recebimento. Corrija <?= count($erros) === 1 ? 'o campo destacado' : 'os campos destacados' ?> abaixo.
    </p>
<?php endif; ?>

<form class="cartao formulario" method="post" enctype="multipart/form-data"
      action="<?= e(url('documentos/receber', ['id' => $d['id']])) ?>" novalidate>
    <?= csrf_campo() ?>

    <fieldset class="<?= e($classe('canal_recebimento')) ?>">
        <legend>Canal de recebimento</legend>
        <div class="opcoes-radio">
            <?php foreach (Documento::CANAIS_MANUAIS as $canal): ?>
                <label class="opcao-radio">
                    <input type="radio" name="canal_recebimento" value="<?= e($canal) ?>"
                        <?= $v('canal_recebimento') === $canal ? 'checked' : '' ?><?= atributos_erro($erros, 'canal_recebimento') ?>>
                    <?= e(Documento::CANAIS[$canal]) ?>
                </label>
            <?php endforeach; ?>
            <label class="opcao-radio opcao-desabilitada" title="Disponível com o portal do cliente (versão 2)">
                <input type="radio" disabled> Sistema <span class="texto-suave texto-pequeno">(portal — versão 2)</span>
            </label>
        </div>
        <?= erro_campo($erros, 'canal_recebimento') ?>
    </fieldset>

    <div class="formulario-grade">
        <div class="<?= e($classe('recebido_em')) ?>">
            <label for="recebido_em">Recebido em</label>
            <input type="datetime-local" id="recebido_em" name="recebido_em" value="<?= e($v('recebido_em')) ?>"
                   max="<?= e(date('Y-m-d\TH:i')) ?>"<?= atributos_erro($erros, 'recebido_em') ?>>
            <?= erro_campo($erros, 'recebido_em') ?>
        </div>

        <div class="<?= e($classe('arquivo')) ?>">
            <label for="arquivo">Arquivo</label>
            <input type="file" id="arquivo" name="arquivo" accept="<?= e(Upload::extensoesAceitas()) ?>"
                   data-limite="<?= e($limite) ?>"<?= atributos_erro($erros, 'arquivo') ?>>
            <p class="campo-ajuda">PDF, JPG, PNG, XML ou XLSX, até <?= e(formatar_tamanho($limite)) ?>.</p>
            <?= erro_campo($erros, 'arquivo') ?>
        </div>
    </div>

    <p class="campo-ajuda">O recebimento será registrado em seu nome e o documento ficará aguardando análise.</p>

    <div class="formulario-acoes">
        <button type="submit" class="botao botao-primario">Registrar recebimento</button>
        <a class="botao botao-secundario" href="<?= e(url('documentos/ver', ['id' => $d['id']])) ?>">Cancelar</a>
    </div>
</form>
