<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Radar Contábil.
 *
 * @var array $radar           resultado completo do RadarService (resumo + clientes)
 * @var array $clientes        clientes após os filtros de risco/tipo
 * @var array $filtros
 * @var array $opcoesClientes
 * @var array $responsaveis
 */
use App\Services\RadarService;

$r       = $radar['resumo'];
$janela  = $radar['janela'];
$analise = $radar['dias_analise'];

// Filtros de escopo (cliente/responsável) são mantidos nos links dos indicadores.
$escopo = array_filter(['cliente_id' => $filtros['cliente_id'], 'responsavel_id' => $filtros['responsavel_id']]);
$linkTipo  = static fn (string $tipo): string => url('radar', $escopo + ['tipo' => $tipo]);
$linkRisco = static fn (string $risco): string => url('radar', $escopo + ['risco' => $risco]);
$filtrosAtivos = array_filter($filtros);

$indicadores = [
    ['obrigacao_atrasada', 'Obrigações atrasadas', $r['obrigacoes_atrasadas'], 'indicador-alto', 'Vencimento anterior a hoje'],
    ['obrigacao_proxima', 'Próximas do vencimento', $r['obrigacoes_proximas'], 'indicador-medio',
        plural($r['vencem_hoje'], 'vence hoje', 'vencem hoje') ?: "Vencem em até {$janela} dias"],
    ['documento_recebimento', 'Documentos aguardando recebimento', $r['documentos_recebimento'], 'indicador-neutro',
        $r['documentos_prazo_vencido'] > 0 ? plural($r['documentos_prazo_vencido'], 'com prazo vencido', 'com prazo vencido') : 'Nenhum com prazo vencido'],
    ['documento_analise', 'Documentos aguardando análise', $r['documentos_analise'], 'indicador-neutro',
        $r['documentos_analise_parada'] > 0 ? plural($r['documentos_analise_parada'], "há mais de {$analise} dias", "há mais de {$analise} dias") : "Nenhum parado há mais de {$analise} dias"],
];
?>
<div class="pagina-cabecalho">
    <div>
        <h1>Radar Contábil</h1>
        <p class="texto-suave">
            Situação em <?= e(date('d/m/Y') . ' às ' . date('H:i')) ?> · calculada a partir das obrigações e documentos cadastrados
        </p>
    </div>
</div>

<section class="radar-resumo" aria-label="Resumo das pendências">
    <?php foreach ($indicadores as [$tipo, $rotulo, $valor, $classe, $complemento]): ?>
        <a class="indicador cartao <?= e($classe) ?>" href="<?= e($linkTipo($tipo)) ?>"
           <?= $filtros['tipo'] === $tipo ? 'aria-current="true"' : '' ?>>
            <span class="indicador-valor"><?= e($valor) ?></span>
            <span class="indicador-rotulo"><?= e($rotulo) ?></span>
            <span class="indicador-complemento"><?= e($complemento) ?></span>
        </a>
    <?php endforeach; ?>

    <div class="indicador cartao indicador-clientes">
        <span class="indicador-rotulo">Clientes que precisam de atenção</span>
        <div class="indicador-riscos">
            <?php foreach (RadarService::RISCOS as $risco => $rotulo): ?>
                <a class="risco risco-<?= e($risco) ?>" href="<?= e($linkRisco($risco)) ?>"
                   <?= $filtros['risco'] === $risco ? 'aria-current="true"' : '' ?>>
                    <strong><?= e($r['clientes_por_risco'][$risco]) ?></strong> <?= e(str_replace(' risco', '', $rotulo)) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <span class="indicador-complemento">
            <?= e(plural($r['clientes_ativos_em_dia'], 'cliente ativo em dia', 'clientes ativos em dia') ?: 'Nenhum cliente ativo sem pendências') ?>
        </span>
    </div>
</section>

<form class="filtros cartao" method="get" action="<?= e(base_path() . 'index.php') ?>" role="search">
    <input type="hidden" name="r" value="radar">

    <div class="campo">
        <label for="cliente_id">Cliente</label>
        <select id="cliente_id" name="cliente_id">
            <option value="">Todos</option>
            <?= opcoes(array_column($opcoesClientes, 'razao_social', 'id'), $filtros['cliente_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="responsavel_id">Responsável</label>
        <select id="responsavel_id" name="responsavel_id">
            <option value="">Todos</option>
            <?= opcoes(array_column($responsaveis, 'nome', 'id'), $filtros['responsavel_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="risco">Nível de risco</label>
        <select id="risco" name="risco">
            <option value="">Todos</option>
            <?= opcoes(RadarService::RISCOS, $filtros['risco']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="tipo">Tipo de pendência</label>
        <select id="tipo" name="tipo">
            <option value="">Todos</option>
            <?= opcoes(RadarService::TIPOS, $filtros['tipo']) ?>
        </select>
    </div>

    <div class="filtros-acoes">
        <button type="submit" class="botao botao-primario">Filtrar</button>
        <?php if ($filtrosAtivos !== []): ?>
            <a class="botao botao-secundario" href="<?= e(url('radar')) ?>">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<h2 class="radar-titulo-lista">
    <?= e(plural(count($clientes), 'cliente', 'clientes') ?: 'Nenhum cliente') ?>
    <span class="texto-suave">· do mais crítico para o menos crítico</span>
</h2>

<?php if ($clientes === []): ?>
    <div class="vazio cartao">
        <?php if ($filtrosAtivos !== []): ?>
            <p><strong>Nenhuma pendência encontrada com esses filtros.</strong></p>
            <p class="texto-suave"><a href="<?= e(url('radar')) ?>">Limpar filtros</a></p>
        <?php else: ?>
            <p><strong>Tudo em dia.</strong></p>
            <p class="texto-suave">Nenhuma obrigação atrasada ou a vencer nos próximos <?= e($janela) ?> dias e nenhum documento pendente.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="radar-clientes">
        <?php foreach ($clientes as $c): ?>
            <article class="radar-cliente cartao risco-borda-<?= e($c['risco']) ?>" aria-labelledby="radar-cliente-<?= e($c['id']) ?>">
                <header class="radar-cliente-cabecalho">
                    <div>
                        <h3 id="radar-cliente-<?= e($c['id']) ?>">
                            <a href="<?= e(url('clientes/ver', ['id' => $c['id']])) ?>"><?= e($c['nome']) ?></a>
                            <?php if ($c['status'] === 'inativo'): ?><span class="texto-suave texto-pequeno">(inativo)</span><?php endif; ?>
                        </h3>
                        <p class="texto-suave texto-pequeno">Responsável: <?= e($c['responsavel_nome']) ?></p>
                    </div>
                    <span class="risco risco-<?= e($c['risco']) ?>"><?= e(RadarService::RISCOS[$c['risco']]) ?></span>
                </header>

                <p class="radar-cliente-contagem texto-pequeno">
                    <?php
                    $rotulosContagem = [
                        'obrigacao_atrasada'    => ['obrigação atrasada', 'obrigações atrasadas'],
                        'obrigacao_proxima'     => ['obrigação próxima do vencimento', 'obrigações próximas do vencimento'],
                        'documento_recebimento' => ['documento aguardando recebimento', 'documentos aguardando recebimento'],
                        'documento_analise'     => ['documento aguardando análise', 'documentos aguardando análise'],
                    ];
                    $partes = [];
                    foreach ($c['por_tipo'] as $tipo => $quantidade) {
                        if ($quantidade > 0) {
                            $partes[] = plural($quantidade, ...$rotulosContagem[$tipo]);
                        }
                    }
                    ?>
                    <?= e(plural($c['total'], 'pendência', 'pendências')) ?>: <?= e(implode(' · ', $partes)) ?>
                </p>

                <ul class="radar-pendencias">
                    <?php foreach ($c['pendencias'] as $p): ?>
                        <?= App\Core\View::renderizar('radar/_pendencia', ['p' => $p]) ?>
                    <?php endforeach; ?>
                </ul>

                <footer class="radar-cliente-links texto-pequeno">
                    <a href="<?= e(url('obrigacoes', ['cliente_id' => $c['id'], 'prazo' => 'em_aberto'])) ?>">Obrigações em aberto</a>
                    <a href="<?= e(url('documentos', ['cliente_id' => $c['id'], 'pendencia' => 'nao_enviados'])) ?>">Documentos não enviados</a>
                    <a href="<?= e(url('clientes/ver', ['id' => $c['id']])) ?>">Ficha do cliente</a>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<details class="cartao radar-regras">
    <summary>Como o nível de risco é calculado</summary>
    <p>Cada obrigação ou documento gera no máximo <strong>uma</strong> pendência, com a gravidade abaixo. O nível de risco do cliente é a <strong>maior gravidade</strong> entre as suas pendências — não há pesos nem pontuação.</p>
    <div class="tabela-container">
        <table class="tabela">
            <thead><tr><th scope="col">Situação</th><th scope="col">Gravidade</th></tr></thead>
            <tbody>
                <tr><td>Obrigação pendente ou em andamento com vencimento anterior a hoje</td><td><span class="risco risco-alto">Alta</span></td></tr>
                <tr><td>Documento solicitado ou rejeitado com prazo de envio vencido</td><td><span class="risco risco-alto">Alta</span></td></tr>
                <tr><td>Obrigação pendente ou em andamento que vence hoje ou em até <?= e($janela) ?> dias</td><td><span class="risco risco-medio">Média</span></td></tr>
                <tr><td>Documento recebido aguardando análise há mais de <?= e($analise) ?> dias</td><td><span class="risco risco-medio">Média</span></td></tr>
                <tr><td>Documento solicitado ou rejeitado dentro do prazo ou sem prazo</td><td><span class="risco risco-baixo">Baixa</span></td></tr>
                <tr><td>Documento recebido aguardando análise há até <?= e($analise) ?> dias</td><td><span class="risco risco-baixo">Baixa</span></td></tr>
            </tbody>
        </table>
    </div>
    <p class="texto-suave texto-pequeno">
        Alto risco: ao menos uma pendência de gravidade alta. Médio risco: nenhuma alta e ao menos uma média.
        Baixo risco: apenas pendências de gravidade baixa. Obrigações concluídas ou canceladas e documentos aprovados não entram no Radar.
        Os prazos (<?= e($janela) ?> e <?= e($analise) ?> dias) vêm da configuração do sistema.
    </p>
</details>
