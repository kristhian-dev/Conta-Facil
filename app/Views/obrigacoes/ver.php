<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Dados completos de uma obrigação.
 *
 * @var array $obrigacao
 * @var int $documentos
 */
use App\Core\Auth;
use App\Models\Obrigacao;

$o      = $obrigacao;
$aberta = in_array($o['status'], Obrigacao::STATUS_ABERTOS, true);
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha"><a href="<?= e(url('obrigacoes')) ?>">Obrigações</a> /</p>
        <h1><?= e($o['tipo_nome']) ?> — <?= e(formatar_competencia($o['competencia'])) ?></h1>
        <p class="celula-etiquetas"><?= App\Core\View::renderizar('obrigacoes/_situacao', ['obrigacao' => $o]) ?></p>
    </div>

    <div class="acoes">
        <?php if ($o['status'] !== 'cancelada' && $o['cliente_status'] === 'ativo'): ?>
            <a class="botao botao-secundario" href="<?= e(url('documentos/novo', ['obrigacao_id' => $o['id']])) ?>">Solicitar documento</a>
        <?php endif; ?>
        <a class="botao botao-primario" href="<?= e(url('obrigacoes/editar', ['id' => $o['id']])) ?>">Editar</a>
        <?php if (Auth::temPerfil(['admin'])): ?>
            <form method="post" action="<?= e(url('obrigacoes/excluir')) ?>"
                  data-confirmar="Excluir esta obrigação (<?= e($o['tipo_nome']) ?> de <?= e(formatar_competencia($o['competencia'])) ?>)? Esta ação não pode ser desfeita.">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                <button type="submit" class="botao botao-perigo">Excluir</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($aberta): ?>
    <section class="cartao painel-acao">
        <h2>Atualizar situação</h2>
        <div class="painel-acao-conteudo">
            <form class="form-linha" method="post" action="<?= e(url('obrigacoes/concluir')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                <div class="campo">
                    <label for="data_conclusao">Data de conclusão</label>
                    <input type="date" id="data_conclusao" name="data_conclusao"
                           value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <button type="submit" class="botao botao-primario">Marcar como concluída</button>
            </form>

            <form method="post" action="<?= e(url('obrigacoes/cancelar')) ?>"
                  data-confirmar="Cancelar esta obrigação? Ela deixará de aparecer como pendência.">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                <button type="submit" class="botao botao-secundario">Cancelar obrigação</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<div class="detalhes-grade">
    <section class="cartao">
        <h2>Obrigação</h2>
        <dl class="detalhes">
            <dt>Tipo</dt>
            <dd>
                <?= e($o['tipo_nome']) ?>
                <?php if ($o['tipo_descricao'] !== null): ?>
                    <span class="texto-suave texto-pequeno bloco"><?= e($o['tipo_descricao']) ?></span>
                <?php endif; ?>
            </dd>

            <dt>Periodicidade</dt>
            <dd><?= e(ucfirst($o['tipo_periodicidade'])) ?></dd>

            <dt>Competência</dt>
            <dd><?= e(formatar_competencia($o['competencia'])) ?></dd>

            <dt>Valor</dt>
            <dd><?= e(formatar_moeda($o['valor'])) ?></dd>
        </dl>
    </section>

    <section class="cartao">
        <h2>Prazo e situação</h2>
        <dl class="detalhes">
            <dt>Vencimento</dt>
            <dd><?= e(formatar_data($o['data_vencimento'])) ?></dd>

            <dt>Situação</dt>
            <dd><?= e(Obrigacao::STATUS[$o['status']]) ?></dd>

            <dt>Concluída em</dt>
            <dd><?= e(formatar_data($o['data_conclusao'])) ?></dd>

            <dt>Responsável</dt>
            <dd><?= e($o['responsavel_nome']) ?></dd>

            <dt>Documentos</dt>
            <dd><a href="<?= e(url('documentos', ['obrigacao_id' => $o['id']])) ?>"><?= e($documentos) ?></a></dd>
        </dl>
    </section>

    <section class="cartao">
        <h2>Cliente</h2>
        <dl class="detalhes">
            <dt>Nome</dt>
            <dd><a href="<?= e(url('clientes/ver', ['id' => $o['cliente_id']])) ?>"><?= e($o['cliente_nome']) ?></a></dd>

            <?php if ($o['cliente_fantasia'] !== null): ?>
                <dt>Nome fantasia</dt>
                <dd><?= e($o['cliente_fantasia']) ?></dd>
            <?php endif; ?>

            <dt>CPF/CNPJ</dt>
            <dd class="texto-numerico"><?= e(formatar_cpf_cnpj($o['cliente_documento'])) ?></dd>

            <dt>Situação</dt>
            <dd><?= $o['cliente_status'] === 'ativo' ? 'Ativo' : 'Inativo' ?></dd>
        </dl>
    </section>

    <section class="cartao">
        <h2>Observações</h2>
        <?php if ($o['observacoes'] !== null): ?>
            <p class="texto-preservado"><?= e($o['observacoes']) ?></p>
        <?php else: ?>
            <p class="texto-suave">Nenhuma observação registrada.</p>
        <?php endif; ?>
        <p class="texto-suave texto-pequeno">
            Cadastrada em <?= e(formatar_data($o['criado_em'], true)) ?> ·
            atualizada em <?= e(formatar_data($o['atualizado_em'], true)) ?>
        </p>
    </section>
</div>
