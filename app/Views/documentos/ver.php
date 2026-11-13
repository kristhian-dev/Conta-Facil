<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Ficha completa de um documento.
 *
 * @var array $documento
 * @var bool $arquivoDisponivel
 * @var array<string, string> $erros   (rejeição)
 * @var string $motivo                 (valor digitado na rejeição)
 */
use App\Core\Auth;
use App\Models\Documento;

$d          = $documento;
$naoEnviado = in_array($d['status'], Documento::NAO_ENVIADOS, true);
$exibivel   = in_array($d['mime_type'], ['application/pdf', 'image/jpeg', 'image/png'], true);
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha"><a href="<?= e(url('documentos')) ?>">Documentos</a> /</p>
        <h1><?= e($d['titulo']) ?></h1>
        <p class="celula-etiquetas"><?= App\Core\View::renderizar('documentos/_situacao', ['documento' => $d]) ?></p>
    </div>

    <div class="acoes">
        <?php if ($naoEnviado): ?>
            <a class="botao botao-primario" href="<?= e(url('documentos/receber', ['id' => $d['id']])) ?>">
                <?= $d['status'] === 'rejeitado' ? 'Registrar novo recebimento' : 'Registrar recebimento' ?>
            </a>
        <?php endif; ?>
        <a class="botao botao-secundario" href="<?= e(url('documentos/editar', ['id' => $d['id']])) ?>">Editar</a>
        <?php if (Auth::temPerfil(['admin'])): ?>
            <form method="post" action="<?= e(url('documentos/excluir')) ?>"
                  data-confirmar="Excluir o documento &quot;<?= e($d['titulo']) ?>&quot;<?= $d['arquivo_armazenado'] !== null ? ' e o arquivo enviado' : '' ?>? Esta ação não pode ser desfeita.">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($d['id']) ?>">
                <button type="submit" class="botao botao-perigo">Excluir</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($d['status'] === 'recebido'): ?>
    <section class="cartao painel-acao" aria-labelledby="titulo-analise">
        <h2 id="titulo-analise">Análise do documento</h2>
        <div class="painel-acao-conteudo">
            <form method="post" action="<?= e(url('documentos/aprovar')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($d['id']) ?>">
                <button type="submit" class="botao botao-primario">Aprovar documento</button>
            </form>

            <form class="form-rejeicao" method="post" action="<?= e(url('documentos/rejeitar')) ?>" novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($d['id']) ?>">
                <div class="campo<?= isset($erros['motivo_rejeicao']) ? ' campo-invalido' : '' ?>">
                    <label for="motivo_rejeicao">Motivo da rejeição</label>
                    <textarea id="motivo_rejeicao" name="motivo_rejeicao" rows="2" maxlength="2000"
                              placeholder="Explique ao cliente o que precisa ser corrigido"
                              <?= atributos_erro($erros, 'motivo_rejeicao') ?>><?= e($motivo) ?></textarea>
                    <?= erro_campo($erros, 'motivo_rejeicao') ?>
                </div>
                <button type="submit" class="botao botao-perigo">Rejeitar documento</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<div class="detalhes-grade">
    <section class="cartao">
        <h2>Solicitação</h2>
        <dl class="detalhes">
            <dt>Cliente</dt>
            <dd><a href="<?= e(url('clientes/ver', ['id' => $d['cliente_id']])) ?>"><?= e($d['cliente_nome']) ?></a>
                <span class="texto-suave texto-pequeno bloco"><?= e(formatar_cpf_cnpj($d['cliente_documento'])) ?></span></dd>

            <dt>Obrigação</dt>
            <dd>
                <?php if ($d['obrigacao_id'] !== null): ?>
                    <a href="<?= e(url('obrigacoes/ver', ['id' => $d['obrigacao_id']])) ?>"><?= e($d['tipo_nome']) ?></a>
                    <span class="texto-suave texto-pequeno bloco">
                        Competência <?= e(formatar_competencia($d['obrigacao_competencia'])) ?> ·
                        vence <?= e(formatar_data($d['obrigacao_vencimento'])) ?>
                    </span>
                <?php else: ?>
                    <span class="texto-suave">Sem vínculo</span>
                <?php endif; ?>
            </dd>

            <dt>Prazo de envio</dt>
            <dd><?= e($d['prazo_envio'] !== null ? formatar_data($d['prazo_envio']) : 'Sem prazo') ?></dd>

            <dt>Solicitado em</dt>
            <dd><?= e(formatar_data($d['criado_em'], true)) ?></dd>
        </dl>
        <?php if ($d['descricao'] !== null): ?>
            <p class="texto-preservado detalhes-nota"><?= e($d['descricao']) ?></p>
        <?php endif; ?>
    </section>

    <section class="cartao">
        <h2>Recebimento</h2>
        <?php if ($d['recebido_em'] === null): ?>
            <p class="texto-suave">Ainda não recebido.</p>
        <?php else: ?>
            <dl class="detalhes">
                <dt>Canal</dt>
                <dd><?= e(Documento::CANAIS[$d['canal_recebimento']] ?? '—') ?></dd>

                <dt>Recebido em</dt>
                <dd><?= e(formatar_data($d['recebido_em'], true)) ?></dd>

                <dt>Registrado por</dt>
                <dd><?= e($d['registrado_por_nome'] ?? '—') ?></dd>

                <dt>Arquivo</dt>
                <dd>
                    <?= e($d['arquivo_original']) ?>
                    <span class="texto-suave texto-pequeno bloco"><?= e(formatar_tamanho($d['tamanho_bytes'])) ?> · <?= e($d['mime_type']) ?></span>
                </dd>
            </dl>
            <?php if ($arquivoDisponivel): ?>
                <div class="acoes detalhes-nota">
                    <?php if ($exibivel): ?>
                        <a class="botao botao-secundario" href="<?= e(url('documentos/arquivo', ['id' => $d['id'], 'modo' => 'ver'])) ?>"
                           target="_blank" rel="noopener">Visualizar</a>
                    <?php endif; ?>
                    <a class="botao botao-secundario" href="<?= e(url('documentos/arquivo', ['id' => $d['id']])) ?>">Baixar</a>
                </div>
            <?php else: ?>
                <p class="alerta alerta-aviso detalhes-nota">O arquivo deste documento não está disponível no servidor.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="cartao">
        <h2>Análise</h2>
        <?php if ($d['analisado_em'] === null): ?>
            <p class="texto-suave"><?= $d['status'] === 'recebido' ? 'Aguardando análise.' : 'Ainda não analisado.' ?></p>
        <?php else: ?>
            <dl class="detalhes">
                <dt>Resultado</dt>
                <dd><?= e(Documento::STATUS[$d['status']]) ?></dd>

                <dt>Analisado por</dt>
                <dd><?= e($d['analisado_por_nome'] ?? '—') ?></dd>

                <dt>Analisado em</dt>
                <dd><?= e(formatar_data($d['analisado_em'], true)) ?></dd>

                <?php if ($d['motivo_rejeicao'] !== null): ?>
                    <dt>Motivo</dt>
                    <dd class="texto-preservado"><?= e($d['motivo_rejeicao']) ?></dd>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
    </section>
</div>
