<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Dados completos de um cliente.
 *
 * @var array $cliente
 * @var array{obrigacoes: int, documentos: int, usuarios: int} $vinculos
 * @var array{numero: string, exibicao: string, fixo: bool, modelos: array}|null $whatsapp
 *      contato pelo WhatsApp (null: cliente inativo ou telefone ausente/inválido)
 */
use App\Core\Auth;
use App\Models\Cliente;

$pf = $cliente['tipo_pessoa'] === 'PF';
?>
<div class="pagina-cabecalho">
    <div>
        <p class="migalha"><a href="<?= e(url('clientes')) ?>">Clientes</a> /</p>
        <h1><?= e($cliente['razao_social']) ?></h1>
        <p>
            <span class="etiqueta etiqueta-<?= e($cliente['status']) ?>"><?= e(Cliente::STATUS[$cliente['status']]) ?></span>
            <?php if ($cliente['nome_fantasia'] !== null): ?>
                <span class="texto-suave"><?= e($cliente['nome_fantasia']) ?></span>
            <?php endif; ?>
        </p>
    </div>

    <div class="acoes">
        <a class="botao botao-primario" href="<?= e(url('clientes/editar', ['id' => $cliente['id']])) ?>">Editar</a>
        <?php if (Auth::temPerfil(['admin'])): ?>
            <form method="post" action="<?= e(url('clientes/excluir')) ?>"
                  data-confirmar="Excluir o cliente &quot;<?= e($cliente['razao_social']) ?>&quot;? Esta ação não pode ser desfeita.">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($cliente['id']) ?>">
                <button type="submit" class="botao botao-perigo">Excluir</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="detalhes-grade">
    <section class="cartao">
        <h2>Identificação</h2>
        <dl class="detalhes">
            <dt>Tipo</dt>
            <dd><?= e(Cliente::TIPOS[$cliente['tipo_pessoa']]) ?></dd>

            <dt><?= $pf ? 'CPF' : 'CNPJ' ?></dt>
            <dd class="texto-numerico"><?= e(formatar_cpf_cnpj($cliente['cpf_cnpj'])) ?></dd>

            <?php if (!$pf): ?>
                <dt>Nome fantasia</dt>
                <dd><?= e($cliente['nome_fantasia'] ?? '—') ?></dd>
            <?php endif; ?>

            <dt>Regime tributário</dt>
            <dd><?= e($cliente['regime_tributario']) ?></dd>
        </dl>
    </section>

    <section class="cartao">
        <h2>Contato</h2>
        <dl class="detalhes">
            <dt>E-mail</dt>
            <dd>
                <?php if ($cliente['email'] !== null): ?>
                    <a href="mailto:<?= e($cliente['email']) ?>"><?= e($cliente['email']) ?></a>
                <?php else: ?>—<?php endif; ?>
            </dd>

            <dt>Telefone</dt>
            <dd><?= e($cliente['telefone'] ?? '—') ?></dd>
        </dl>

        <?php if ($cliente['status'] !== 'ativo'): ?>
            <p class="texto-suave texto-pequeno detalhes-nota">Cliente inativo: contato pelo WhatsApp indisponível.</p>
        <?php elseif ($whatsapp === null): ?>
            <p class="alerta alerta-aviso detalhes-nota">
                Telefone ausente ou inválido para WhatsApp (informe DDD e número).
                <a href="<?= e(url('clientes/editar', ['id' => $cliente['id']])) ?>">Editar cadastro</a>
            </p>
        <?php else: ?>
            <div class="acoes detalhes-nota">
                <a class="botao botao-secundario" target="_blank" rel="noopener noreferrer"
                   href="<?= e(link_whatsapp($whatsapp['numero'], $whatsapp['modelos'][0]['texto'])) ?>">
                    Abrir WhatsApp · <?= e($whatsapp['exibicao']) ?>
                </a>
            </div>
            <?php if ($whatsapp['fixo']): ?>
                <p class="campo-ajuda">Telefone fixo: este número pode não ter WhatsApp.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="cartao">
        <h2>Acompanhamento</h2>
        <dl class="detalhes">
            <dt>Responsável</dt>
            <dd><?= e($cliente['responsavel_nome']) ?></dd>

            <dt>Obrigações</dt>
            <dd><?= e($vinculos['obrigacoes']) ?></dd>

            <dt>Documentos</dt>
            <dd><a href="<?= e(url('documentos', ['cliente_id' => $cliente['id']])) ?>"><?= e($vinculos['documentos']) ?></a></dd>

            <dt>Cadastrado em</dt>
            <dd><?= e(formatar_data($cliente['criado_em'], true)) ?></dd>

            <dt>Última atualização</dt>
            <dd><?= e(formatar_data($cliente['atualizado_em'], true)) ?></dd>
        </dl>
    </section>

    <section class="cartao">
        <h2>Observações</h2>
        <?php if ($cliente['observacoes'] !== null): ?>
            <p class="texto-preservado"><?= e($cliente['observacoes']) ?></p>
        <?php else: ?>
            <p class="texto-suave">Nenhuma observação registrada.</p>
        <?php endif; ?>
    </section>

    <?php if ($whatsapp !== null): ?>
        <?php /* Link wa.me: o WhatsApp abre com o texto; o envio é sempre feito pelo contador. */ ?>
        <section class="cartao campo-largo" id="whatsapp" aria-labelledby="whatsapp-titulo" data-whatsapp="<?= e($whatsapp['numero']) ?>">
            <h2 id="whatsapp-titulo">Mensagem pelo WhatsApp</h2>
            <p class="campo-ajuda">
                Escolha um modelo e revise o texto. O WhatsApp abre com a mensagem pronta para você conferir e enviar.
                O ContaFácil não envia nem guarda mensagens.
            </p>

            <div class="formulario-grade detalhes-nota">
                <div class="campo">
                    <label for="whatsapp-modelo">Modelo</label>
                    <select id="whatsapp-modelo" data-whatsapp-modelo>
                        <?php foreach ($whatsapp['modelos'] as $modelo): ?>
                            <option value="<?= e($modelo['chave']) ?>" data-mensagem="<?= e($modelo['texto']) ?>"><?= e($modelo['rotulo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($whatsapp['modelos']) === 1): ?>
                        <p class="campo-ajuda">Sem pendências do cliente no momento.</p>
                    <?php endif; ?>
                </div>

                <div class="campo campo-largo">
                    <label for="whatsapp-mensagem">Mensagem</label>
                    <textarea id="whatsapp-mensagem" rows="7" maxlength="1500" data-whatsapp-mensagem><?= e($whatsapp['modelos'][0]['texto']) ?></textarea>
                </div>
            </div>

            <div class="acoes">
                <a class="botao botao-primario" target="_blank" rel="noopener noreferrer" data-whatsapp-link
                   href="<?= e(link_whatsapp($whatsapp['numero'], $whatsapp['modelos'][0]['texto'])) ?>">Abrir no WhatsApp</a>
                <span class="texto-suave texto-pequeno">Para <?= e($whatsapp['exibicao']) ?><?= $whatsapp['fixo'] ? ' (telefone fixo: pode não ter WhatsApp)' : '' ?></span>
            </div>
        </section>
    <?php endif; ?>
</div>
