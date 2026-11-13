<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Listagem de documentos.
 *
 * @var array<int, array> $documentos
 * @var array $filtros
 * @var App\Core\Paginator $paginacao
 * @var array $contagem
 * @var array $clientes
 * @var array $obrigacoes
 */
use App\Models\Documento;

$filtrosAtivos = array_filter(
    $filtros,
    static fn (mixed $valor, string $chave): bool => $chave !== 'ordenar' && $valor !== '' && $valor !== 0,
    ARRAY_FILTER_USE_BOTH
);
$parametrosPaginacao = $filtrosAtivos + ($filtros['ordenar'] !== 'prioridade' ? ['ordenar' => $filtros['ordenar']] : []);

$opcoesClientes = [];
foreach ($clientes as $c) {
    $opcoesClientes[$c['id']] = $c['razao_social'] . ($c['status'] === 'inativo' ? ' (inativo)' : '');
}
$opcoesObrigacoes = [];
foreach ($obrigacoes as $o) {
    $opcoesObrigacoes[$o['id']] = $o['tipo_nome'] . ' ' . formatar_competencia($o['competencia']) . ' — ' . $o['cliente_nome'];
}

$atalhos = [
    [['status' => 'solicitado'], 'Aguardando recebimento', $contagem['solicitado'], 'atalho-proxima'],
    [['status' => 'recebido'], 'Aguardando análise', $contagem['recebido'], 'atalho-info'],
    [['status' => 'rejeitado'], 'Rejeitados', $contagem['rejeitado'], 'atalho-atrasada'],
    [['pendencia' => 'prazo_vencido'], 'Prazo de envio vencido', $contagem['prazo_vencido'], 'atalho-atrasada'],
    [['pendencia' => 'analise_parada'], 'Análise há mais de ' . Documento::diasAnaliseParada() . ' dias', $contagem['analise_parada'], 'atalho-hoje'],
];

$classeLinha = static function (array $d): string {
    $pendencia = Documento::pendencia($d);
    return match (true) {
        $d['status'] === 'rejeitado', ($pendencia['tipo'] ?? '') === 'prazo_vencido' => 'linha-atrasada',
        $d['status'] === 'solicitado' => 'linha-proxima',
        $d['status'] === 'recebido'   => 'linha-info',
        default                       => '',
    };
};
?>
<div class="pagina-cabecalho">
    <div>
        <h1>Documentos</h1>
        <p class="texto-suave">
            <?= e($paginacao->total) ?> <?= $paginacao->total === 1 ? 'documento encontrado' : 'documentos encontrados' ?>
        </p>
    </div>
    <a class="botao botao-primario" href="<?= e(url('documentos/novo')) ?>">+ Solicitar documento</a>
</div>

<nav class="atalhos" aria-label="Atalhos de pendências">
    <?php foreach ($atalhos as [$parametros, $rotulo, $quantidade, $classe]): ?>
        <?php $atual = array_intersect_assoc($parametros, $filtros) === $parametros; ?>
        <a class="atalho <?= e($classe) ?>" href="<?= e(url('documentos', $parametros)) ?>"<?= $atual ? ' aria-current="true"' : '' ?>>
            <strong><?= e($quantidade) ?></strong> <?= e($rotulo) ?>
        </a>
    <?php endforeach; ?>
</nav>

<form class="filtros cartao" method="get" action="<?= e(base_path() . 'index.php') ?>" role="search">
    <input type="hidden" name="r" value="documentos">

    <div class="campo filtros-busca">
        <label for="busca">Pesquisar</label>
        <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>"
               maxlength="100" placeholder="Título, descrição, nome do arquivo, cliente ou CPF/CNPJ">
    </div>

    <div class="campo">
        <label for="cliente_id">Cliente</label>
        <select id="cliente_id" name="cliente_id">
            <option value="">Todos</option>
            <?= opcoes($opcoesClientes, $filtros['cliente_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="obrigacao_id">Obrigação</label>
        <select id="obrigacao_id" name="obrigacao_id">
            <option value="">Todas</option>
            <?= opcoes($opcoesObrigacoes, $filtros['obrigacao_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
            <option value="">Todas</option>
            <?= opcoes(Documento::STATUS, $filtros['status']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="pendencia">Pendência</label>
        <select id="pendencia" name="pendencia">
            <option value="">Qualquer</option>
            <?= opcoes(Documento::PENDENCIAS, $filtros['pendencia']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="canal_recebimento">Canal</label>
        <select id="canal_recebimento" name="canal_recebimento">
            <option value="">Todos</option>
            <?= opcoes(Documento::CANAIS, $filtros['canal_recebimento']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="ordenar">Ordenar por</label>
        <select id="ordenar" name="ordenar">
            <?= opcoes([
                'prioridade'  => 'Prioridade (pendências primeiro)',
                'recentes'    => 'Solicitados mais recentemente',
                'recebimento' => 'Recebidos mais recentemente',
                'cliente'     => 'Cliente (A–Z)',
            ], $filtros['ordenar']) ?>
        </select>
    </div>

    <div class="filtros-acoes">
        <button type="submit" class="botao botao-primario">Filtrar</button>
        <?php if ($filtrosAtivos !== [] || $filtros['ordenar'] !== 'prioridade'): ?>
            <a class="botao botao-secundario" href="<?= e(url('documentos')) ?>">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($documentos === []): ?>
    <div class="vazio cartao">
        <?php if ($filtrosAtivos !== []): ?>
            <p><strong>Nenhum documento encontrado com esses critérios.</strong></p>
            <p class="texto-suave">Revise a pesquisa ou <a href="<?= e(url('documentos')) ?>">limpe os filtros</a>.</p>
        <?php else: ?>
            <p><strong>Nenhum documento solicitado ainda.</strong></p>
            <p class="texto-suave"><a href="<?= e(url('documentos/novo')) ?>">Solicite o primeiro documento</a>.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="tabela-container cartao">
        <table class="tabela tabela-responsiva">
            <thead>
                <tr>
                    <th scope="col">Documento</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Obrigação</th>
                    <th scope="col">Situação</th>
                    <th scope="col">Prazo / recebimento</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documentos as $d): ?>
                    <?php $classe = $classeLinha($d); ?>
                    <tr<?= $classe !== '' ? ' class="' . e($classe) . '"' : '' ?>>
                        <td data-rotulo="Documento">
                            <a class="tabela-titulo" href="<?= e(url('documentos/ver', ['id' => $d['id']])) ?>"><?= e($d['titulo']) ?></a>
                            <?php if ($d['arquivo_original'] !== null): ?>
                                <span class="texto-suave texto-pequeno"><?= e($d['arquivo_original']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-rotulo="Cliente"><?= e($d['cliente_nome']) ?></td>
                        <td data-rotulo="Obrigação">
                            <?= $d['obrigacao_id'] !== null
                                ? e($d['tipo_nome'] . ' ' . formatar_competencia($d['obrigacao_competencia']))
                                : '<span class="texto-suave">—</span>' ?>
                        </td>
                        <td data-rotulo="Situação">
                            <span class="etiquetas"><?= App\Core\View::renderizar('documentos/_situacao', ['documento' => $d]) ?></span>
                        </td>
                        <td data-rotulo="Prazo / recebimento" class="texto-pequeno">
                            <?php if ($d['recebido_em'] !== null): ?>
                                Recebido em <?= e(formatar_data($d['recebido_em'])) ?>
                                <span class="texto-suave">(<?= e(Documento::CANAIS[$d['canal_recebimento']] ?? '—') ?>)</span>
                            <?php elseif ($d['prazo_envio'] !== null): ?>
                                Prazo: <?= e(formatar_data($d['prazo_envio'])) ?>
                            <?php else: ?>
                                <span class="texto-suave">Sem prazo</span>
                            <?php endif; ?>
                        </td>
                        <td class="tabela-acoes">
                            <a href="<?= e(url('documentos/ver', ['id' => $d['id']])) ?>">Ver</a>
                            <?php if (in_array($d['status'], Documento::NAO_ENVIADOS, true)): ?>
                                <a href="<?= e(url('documentos/receber', ['id' => $d['id']])) ?>">Receber</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= App\Core\View::renderizar('partials/paginacao', [
        'paginacao'  => $paginacao,
        'rota'       => 'documentos',
        'parametros' => $parametrosPaginacao,
    ]) ?>
<?php endif; ?>
