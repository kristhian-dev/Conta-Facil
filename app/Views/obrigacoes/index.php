<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Listagem de obrigações.
 *
 * @var array<int, array> $obrigacoes
 * @var array $filtros
 * @var App\Core\Paginator $paginacao
 * @var array{atrasada: int, vence_hoje: int, proxima: int} $contagem
 * @var array $clientes
 * @var array $tipos
 * @var array $responsaveis
 */
use App\Models\Obrigacao;

$janela = Obrigacao::janela();

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

$atalhos = [
    'atrasada'   => ['Atrasadas', $contagem['atrasada'], 'atalho-atrasada'],
    'vence_hoje' => ['Vencem hoje', $contagem['vence_hoje'], 'atalho-hoje'],
    'proxima'    => ["Próximos {$janela} dias", $contagem['proxima'], 'atalho-proxima'],
];
?>
<div class="pagina-cabecalho">
    <div>
        <h1>Obrigações</h1>
        <p class="texto-suave">
            <?= e($paginacao->total) ?> <?= $paginacao->total === 1 ? 'obrigação encontrada' : 'obrigações encontradas' ?>
        </p>
    </div>
    <a class="botao botao-primario" href="<?= e(url('obrigacoes/nova')) ?>">+ Nova obrigação</a>
</div>

<nav class="atalhos" aria-label="Atalhos de prazo">
    <?php foreach ($atalhos as $prazo => [$rotulo, $quantidade, $classe]): ?>
        <a class="atalho <?= e($classe) ?>" href="<?= e(url('obrigacoes', ['prazo' => $prazo])) ?>"
           <?= $filtros['prazo'] === $prazo ? 'aria-current="true"' : '' ?>>
            <strong><?= e($quantidade) ?></strong> <?= e($rotulo) ?>
        </a>
    <?php endforeach; ?>
</nav>

<form class="filtros cartao" method="get" action="<?= e(base_path() . 'index.php') ?>" role="search">
    <input type="hidden" name="r" value="obrigacoes">

    <div class="campo filtros-busca">
        <label for="busca">Pesquisar</label>
        <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>"
               maxlength="100" placeholder="Cliente, CPF/CNPJ, tipo de obrigação ou observação">
    </div>

    <div class="campo">
        <label for="cliente_id">Cliente</label>
        <select id="cliente_id" name="cliente_id">
            <option value="">Todos</option>
            <?= opcoes($opcoesClientes, $filtros['cliente_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="tipo_obrigacao_id">Tipo</label>
        <select id="tipo_obrigacao_id" name="tipo_obrigacao_id">
            <option value="">Todos</option>
            <?= opcoes(array_column($tipos, 'nome', 'id'), $filtros['tipo_obrigacao_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
            <option value="">Todas</option>
            <?= opcoes(Obrigacao::STATUS, $filtros['status']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="prazo">Prazo</label>
        <select id="prazo" name="prazo">
            <option value="">Qualquer</option>
            <?= opcoes(array_replace(Obrigacao::PRAZOS, ['proxima' => "Vencem em 1 a {$janela} dias"]), $filtros['prazo']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="competencia">Competência</label>
        <input type="month" id="competencia" name="competencia" value="<?= e($filtros['competencia']) ?>" placeholder="AAAA-MM">
    </div>

    <div class="campo">
        <label for="vencimento_de">Vencimento de</label>
        <input type="date" id="vencimento_de" name="vencimento_de" value="<?= e($filtros['vencimento_de']) ?>">
    </div>

    <div class="campo">
        <label for="vencimento_ate">Vencimento até</label>
        <input type="date" id="vencimento_ate" name="vencimento_ate" value="<?= e($filtros['vencimento_ate']) ?>">
    </div>

    <div class="campo">
        <label for="responsavel_id">Responsável</label>
        <select id="responsavel_id" name="responsavel_id">
            <option value="">Todos</option>
            <?= opcoes(array_column($responsaveis, 'nome', 'id'), $filtros['responsavel_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="ordenar">Ordenar por</label>
        <select id="ordenar" name="ordenar">
            <?= opcoes([
                'prioridade'      => 'Prioridade (em aberto primeiro)',
                'vencimento'      => 'Vencimento (mais antigo)',
                'vencimento_desc' => 'Vencimento (mais recente)',
                'competencia'     => 'Competência (mais recente)',
                'cliente'         => 'Cliente (A–Z)',
            ], $filtros['ordenar']) ?>
        </select>
    </div>

    <div class="filtros-acoes">
        <button type="submit" class="botao botao-primario">Filtrar</button>
        <?php if ($filtrosAtivos !== [] || $filtros['ordenar'] !== 'prioridade'): ?>
            <a class="botao botao-secundario" href="<?= e(url('obrigacoes')) ?>">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($obrigacoes === []): ?>
    <div class="vazio cartao">
        <?php if ($filtrosAtivos !== []): ?>
            <p><strong>Nenhuma obrigação encontrada com esses critérios.</strong></p>
            <p class="texto-suave">Revise a pesquisa ou <a href="<?= e(url('obrigacoes')) ?>">limpe os filtros</a>.</p>
        <?php else: ?>
            <p><strong>Nenhuma obrigação cadastrada ainda.</strong></p>
            <p class="texto-suave"><a href="<?= e(url('obrigacoes/nova')) ?>">Cadastre a primeira obrigação</a>.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="tabela-container cartao">
        <table class="tabela tabela-responsiva">
            <thead>
                <tr>
                    <th scope="col">Obrigação</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Competência</th>
                    <th scope="col">Vencimento</th>
                    <th scope="col">Valor</th>
                    <th scope="col">Situação</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($obrigacoes as $o): ?>
                    <?php $prazo = Obrigacao::prazo($o); ?>
                    <tr<?= in_array($prazo, ['atrasada', 'vence_hoje', 'proxima'], true) ? ' class="linha-' . e($prazo) . '"' : '' ?>>
                        <td data-rotulo="Obrigação">
                            <a class="tabela-titulo" href="<?= e(url('obrigacoes/ver', ['id' => $o['id']])) ?>"><?= e($o['tipo_nome']) ?></a>
                        </td>
                        <td data-rotulo="Cliente"><?= e($o['cliente_nome']) ?></td>
                        <td data-rotulo="Competência" class="texto-numerico"><?= e(formatar_competencia($o['competencia'])) ?></td>
                        <td data-rotulo="Vencimento" class="texto-numerico"><?= e(formatar_data($o['data_vencimento'])) ?></td>
                        <td data-rotulo="Valor" class="texto-numerico"><?= e(formatar_moeda($o['valor'])) ?></td>
                        <td data-rotulo="Situação">
                            <span class="etiquetas"><?= App\Core\View::renderizar('obrigacoes/_situacao', ['obrigacao' => $o]) ?></span>
                        </td>
                        <td class="tabela-acoes">
                            <a href="<?= e(url('obrigacoes/ver', ['id' => $o['id']])) ?>">Ver</a>
                            <a href="<?= e(url('obrigacoes/editar', ['id' => $o['id']])) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= App\Core\View::renderizar('partials/paginacao', [
        'paginacao'  => $paginacao,
        'rota'       => 'obrigacoes',
        'parametros' => $parametrosPaginacao,
    ]) ?>
<?php endif; ?>
