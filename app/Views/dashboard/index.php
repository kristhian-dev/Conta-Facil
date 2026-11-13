<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Painel inicial (dashboard).
 *
 * @var array $painel  dados do DashboardService
 * @var array $usuario usuário logado
 */
use App\Services\RadarService;

$c = $painel['clientes'];
$o = $painel['obrigacoes'];
$d = $painel['documentos'];
$r = $painel['radar'];
$janela = $painel['janela'];
$analise = App\Models\Documento::diasAnaliseParada();

/** Indicador: [rótulo, valor, link, marcador (cor do estado), complemento] */
$grupos = [
    'obrigacoes' => ['Obrigações', 'painel-grupo-largo', [
        ['Em aberto', $o['em_aberto'], url('obrigacoes', ['prazo' => 'em_aberto']), 'primaria', 'Pendentes ou em andamento'],
        ['Atrasadas', $o['atrasada'], url('obrigacoes', ['prazo' => 'atrasada']), 'erro', 'Vencimento anterior a hoje'],
        ['Vencem hoje', $o['vence_hoje'], url('obrigacoes', ['prazo' => 'vence_hoje']), 'alerta', formatar_data(date('Y-m-d'))],
        ["Próximos {$janela} dias", $o['proxima'], url('obrigacoes', ['prazo' => 'proxima']), 'aviso',
            'Até ' . formatar_data(date('Y-m-d', strtotime("+{$janela} days")))],
        ['Concluídas', $o['concluida'], url('obrigacoes', ['status' => 'concluida']), 'sucesso', plural($o['total'], 'obrigação cadastrada', 'obrigações cadastradas') ?: 'Nenhuma cadastrada'],
    ]],
    'clientes' => ['Clientes', '', [
        ['Cadastrados', $c['total'], url('clientes'), 'primaria', 'Total na carteira'],
        ['Ativos', $c['ativos'], url('clientes', ['status' => 'ativo']), 'sucesso', 'Em acompanhamento'],
        ['Inativos', $c['inativos'], url('clientes', ['status' => 'inativo']), 'neutro', 'Mantidos para histórico'],
    ]],
    'documentos' => ['Documentos', '', [
        ['Aguardando recebimento', $d['solicitado'], url('documentos', ['status' => 'solicitado']), 'aviso', 'Solicitados ao cliente'],
        ['Aguardando análise', $d['recebido'], url('documentos', ['status' => 'recebido']), 'info',
            $d['analise_parada'] > 0 ? plural($d['analise_parada'], "há mais de {$analise} dias", "há mais de {$analise} dias") : 'Nenhum parado'],
        ['Aprovados', $d['aprovado'], url('documentos', ['status' => 'aprovado']), 'sucesso', 'Análise concluída'],
        ['Rejeitados', $d['rejeitado'], url('documentos', ['status' => 'rejeitado']), 'erro', 'Aguardando reenvio'],
    ]],
];

$atalhos = [
    ['+ Cadastrar cliente', url('clientes/novo'), 'botao-primario'],
    ['+ Cadastrar obrigação', url('obrigacoes/nova'), 'botao-secundario'],
    ['+ Solicitar documento', url('documentos/novo'), 'botao-secundario'],
    ['Abrir Radar', url('radar'), 'botao-secundario'],
    ['Consultar clientes', url('clientes'), 'botao-secundario'],
];
?>
<div class="pagina-cabecalho">
    <div>
        <h1>Painel do escritório</h1>
        <p class="texto-suave">Olá, <?= e($usuario['nome'] ?? '') ?> · situação em <?= e(date('d/m/Y') . ' às ' . date('H:i')) ?></p>
    </div>
</div>

<nav class="atalhos-rapidos" aria-label="Atalhos rápidos">
    <?php foreach ($atalhos as [$rotulo, $link, $classe]): ?>
        <a class="botao <?= e($classe) ?>" href="<?= e($link) ?>"><?= e($rotulo) ?></a>
    <?php endforeach; ?>
</nav>

<div class="painel-indicadores">
    <?php foreach ($grupos as $chave => [$titulo, $classeGrupo, $indicadores]): ?>
        <section class="cartao painel-grupo <?= e($classeGrupo) ?>" aria-labelledby="grupo-<?= e($chave) ?>">
            <h2 id="grupo-<?= e($chave) ?>"><?= e($titulo) ?></h2>
            <div class="kpis">
                <?php foreach ($indicadores as [$rotulo, $valor, $link, $marcador, $complemento]): ?>
                    <a class="kpi" href="<?= e($link) ?>">
                        <span class="kpi-rotulo"><span class="marcador marcador-<?= e($marcador) ?>" aria-hidden="true"></span><?= e($rotulo) ?></span>
                        <span class="kpi-valor"><?= e($valor) ?></span>
                        <span class="kpi-complemento"><?= e($complemento) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<section class="cartao painel-radar" aria-labelledby="painel-radar-titulo">
    <header class="painel-secao-cabecalho">
        <h2 id="painel-radar-titulo">Radar Contábil</h2>
        <a href="<?= e(url('radar')) ?>">Abrir Radar completo →</a>
    </header>

    <div class="painel-radar-conteudo">
        <div class="painel-radar-resumo">
            <p class="painel-radar-total">
                <span class="kpi-valor"><?= e($r['resumo']['total_pendencias']) ?></span>
                <span><?= $r['resumo']['total_pendencias'] === 1 ? 'pendência identificada' : 'pendências identificadas' ?></span>
            </p>
            <ul class="painel-riscos">
                <?php foreach (RadarService::RISCOS as $risco => $rotulo): ?>
                    <li>
                        <a href="<?= e(url('radar', ['risco' => $risco])) ?>">
                            <span class="risco risco-<?= e($risco) ?>"><?= e($rotulo) ?></span>
                            <strong><?= e($r['resumo']['clientes_por_risco'][$risco]) ?></strong>
                            <?= $r['resumo']['clientes_por_risco'][$risco] === 1 ? 'cliente' : 'clientes' ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="texto-suave texto-pequeno">
                <?= e(plural($r['resumo']['clientes_ativos_em_dia'], 'cliente ativo em dia', 'clientes ativos em dia') ?: 'Nenhum cliente ativo sem pendências') ?>
            </p>
        </div>

        <div class="painel-radar-lista">
            <h3>Clientes que mais precisam de atenção</h3>
            <?php if ($r['clientes'] === []): ?>
                <p class="texto-suave">Nenhuma pendência no momento. Tudo em dia.</p>
            <?php else: ?>
                <ol class="painel-lista">
                    <?php foreach ($r['clientes'] as $cliente): ?>
                        <li class="painel-item">
                            <div class="painel-item-principal">
                                <a href="<?= e(url('clientes/ver', ['id' => $cliente['id']])) ?>"><?= e($cliente['nome']) ?></a>
                                <span class="texto-suave texto-pequeno">
                                    <?= e(plural($cliente['total'], 'pendência', 'pendências')) ?><?php
                                    $graves = array_filter([
                                        plural($cliente['por_gravidade']['alta'], 'alta', 'altas'),
                                        plural($cliente['por_gravidade']['media'], 'média', 'médias'),
                                    ]);
                                    echo $graves !== [] ? ' · ' . e(implode(', ', $graves)) : '';
                                    ?>
                                </span>
                            </div>
                            <div class="painel-item-lateral">
                                <span class="risco risco-<?= e($cliente['risco']) ?>"><?= e(RadarService::RISCOS[$cliente['risco']]) ?></span>
                                <a class="texto-pequeno" href="<?= e(url('radar', ['cliente_id' => $cliente['id']])) ?>">Ver no Radar</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($r['total_clientes'] > count($r['clientes'])): ?>
                    <p class="texto-pequeno"><a href="<?= e(url('radar')) ?>">
                        + <?= e(plural($r['total_clientes'] - count($r['clientes']), 'outro cliente', 'outros clientes')) ?> no Radar
                    </a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="painel-listas">
    <section class="cartao" aria-labelledby="painel-obrigacoes-titulo">
        <header class="painel-secao-cabecalho">
            <h2 id="painel-obrigacoes-titulo">Próximos vencimentos</h2>
            <a href="<?= e(url('obrigacoes', ['prazo' => 'em_aberto', 'ordenar' => 'vencimento'])) ?>">Ver todas →</a>
        </header>
        <p class="texto-suave texto-pequeno">Obrigações em aberto, das atrasadas para as que vencem depois.</p>
        <?php if ($painel['proximas_obrigacoes'] === []): ?>
            <p class="texto-suave">Nenhuma obrigação em aberto.</p>
        <?php else: ?>
            <ul class="painel-lista">
                <?php foreach ($painel['proximas_obrigacoes'] as $ob): ?>
                    <li class="painel-item">
                        <div class="painel-item-principal">
                            <a href="<?= e(url('obrigacoes/ver', ['id' => $ob['id']])) ?>"><?= e($ob['tipo_nome'] . ' ' . formatar_competencia($ob['competencia'])) ?></a>
                            <span class="texto-suave texto-pequeno"><?= e($ob['cliente_nome']) ?> · vence <?= e(formatar_data($ob['data_vencimento'])) ?></span>
                        </div>
                        <div class="painel-item-lateral etiquetas">
                            <?= App\Core\View::renderizar('obrigacoes/_situacao', ['obrigacao' => $ob]) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="cartao" aria-labelledby="painel-documentos-titulo">
        <header class="painel-secao-cabecalho">
            <h2 id="painel-documentos-titulo">Documentos pendentes</h2>
            <a href="<?= e(url('documentos')) ?>">Ver todos →</a>
        </header>
        <p class="texto-suave texto-pequeno">Aguardando recebimento, reenvio ou análise, por prioridade.</p>
        <?php if ($painel['documentos_pendentes'] === []): ?>
            <p class="texto-suave">Nenhum documento pendente.</p>
        <?php else: ?>
            <ul class="painel-lista">
                <?php foreach ($painel['documentos_pendentes'] as $doc): ?>
                    <li class="painel-item">
                        <div class="painel-item-principal">
                            <a href="<?= e(url('documentos/ver', ['id' => $doc['id']])) ?>"><?= e($doc['titulo']) ?></a>
                            <span class="texto-suave texto-pequeno">
                                <?= e($doc['cliente_nome']) ?> ·
                                <?= e($doc['recebido_em'] !== null && $doc['status'] === 'recebido'
                                    ? 'recebido em ' . formatar_data($doc['recebido_em'])
                                    : ($doc['prazo_envio'] !== null ? 'prazo ' . formatar_data($doc['prazo_envio']) : 'sem prazo')) ?>
                            </span>
                        </div>
                        <div class="painel-item-lateral etiquetas">
                            <?= App\Core\View::renderizar('documentos/_situacao', ['documento' => $doc]) ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
