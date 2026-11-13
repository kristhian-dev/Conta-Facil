<?php defined('APP_ROOT') || exit; ?>
<?php
/**
 * Listagem de clientes.
 *
 * @var array<int, array> $clientes
 * @var array $filtros
 * @var App\Core\Paginator $paginacao
 * @var array<int, array{id: int, nome: string}> $responsaveis
 */
use App\Models\Cliente;

$regimes = array_combine(
    [...Cliente::REGIMES_PJ, Cliente::REGIME_PF],
    [...Cliente::REGIMES_PJ, Cliente::REGIME_PF]
);
$opcoesResponsaveis = array_column($responsaveis, 'nome', 'id');

// Filtros ativos (para manter na paginação e para o texto do estado vazio).
$filtrosAtivos = array_filter(
    $filtros,
    static fn (mixed $valor, string $chave): bool => $chave !== 'ordenar' && $valor !== '' && $valor !== 0,
    ARRAY_FILTER_USE_BOTH
);
$parametrosPaginacao = $filtrosAtivos + ($filtros['ordenar'] !== 'nome' ? ['ordenar' => $filtros['ordenar']] : []);
?>
<div class="pagina-cabecalho">
    <div>
        <h1>Clientes</h1>
        <p class="texto-suave">
            <?= e($paginacao->total) ?> <?= $paginacao->total === 1 ? 'cliente encontrado' : 'clientes encontrados' ?>
        </p>
    </div>
    <a class="botao botao-primario" href="<?= e(url('clientes/novo')) ?>">+ Novo cliente</a>
</div>

<form class="filtros cartao" method="get" action="<?= e(base_path() . 'index.php') ?>" role="search">
    <input type="hidden" name="r" value="clientes">

    <div class="campo filtros-busca">
        <label for="busca">Pesquisar</label>
        <input type="search" id="busca" name="busca" value="<?= e($filtros['busca']) ?>"
               maxlength="100" placeholder="Nome, nome fantasia, CPF ou CNPJ">
    </div>

    <div class="campo">
        <label for="tipo_pessoa">Tipo</label>
        <select id="tipo_pessoa" name="tipo_pessoa">
            <option value="">Todos</option>
            <?= opcoes(Cliente::TIPOS, $filtros['tipo_pessoa']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="regime_tributario">Regime</label>
        <select id="regime_tributario" name="regime_tributario">
            <option value="">Todos</option>
            <?= opcoes($regimes, $filtros['regime_tributario']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="status">Situação</label>
        <select id="status" name="status">
            <option value="">Todas</option>
            <?= opcoes(Cliente::STATUS, $filtros['status']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="responsavel_id">Responsável</label>
        <select id="responsavel_id" name="responsavel_id">
            <option value="">Todos</option>
            <?= opcoes($opcoesResponsaveis, $filtros['responsavel_id']) ?>
        </select>
    </div>

    <div class="campo">
        <label for="ordenar">Ordenar por</label>
        <select id="ordenar" name="ordenar">
            <?= opcoes(['nome' => 'Nome (A–Z)', 'recentes' => 'Mais recentes'], $filtros['ordenar']) ?>
        </select>
    </div>

    <div class="filtros-acoes">
        <button type="submit" class="botao botao-primario">Filtrar</button>
        <?php if ($filtrosAtivos !== [] || $filtros['ordenar'] !== 'nome'): ?>
            <a class="botao botao-secundario" href="<?= e(url('clientes')) ?>">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($clientes === []): ?>
    <div class="vazio cartao">
        <?php if ($filtrosAtivos !== []): ?>
            <p><strong>Nenhum cliente encontrado com esses critérios.</strong></p>
            <p class="texto-suave">Revise a pesquisa ou <a href="<?= e(url('clientes')) ?>">limpe os filtros</a>.</p>
        <?php else: ?>
            <p><strong>Nenhum cliente cadastrado ainda.</strong></p>
            <p class="texto-suave"><a href="<?= e(url('clientes/novo')) ?>">Cadastre o primeiro cliente</a>.</p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="tabela-container cartao">
        <table class="tabela tabela-responsiva">
            <thead>
                <tr>
                    <th scope="col">Cliente</th>
                    <th scope="col">CPF/CNPJ</th>
                    <th scope="col">Tipo</th>
                    <th scope="col">Regime</th>
                    <th scope="col">Responsável</th>
                    <th scope="col">Situação</th>
                    <th scope="col"><span class="sr-only">Ações</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td data-rotulo="Cliente">
                            <a class="tabela-titulo" href="<?= e(url('clientes/ver', ['id' => $cliente['id']])) ?>">
                                <?= e($cliente['razao_social']) ?>
                            </a>
                            <?php if ($cliente['nome_fantasia'] !== null): ?>
                                <span class="texto-suave texto-pequeno"><?= e($cliente['nome_fantasia']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-rotulo="CPF/CNPJ" class="texto-numerico"><?= e(formatar_cpf_cnpj($cliente['cpf_cnpj'])) ?></td>
                        <td data-rotulo="Tipo"><?= e($cliente['tipo_pessoa']) ?></td>
                        <td data-rotulo="Regime"><?= e($cliente['regime_tributario']) ?></td>
                        <td data-rotulo="Responsável"><?= e($cliente['responsavel_nome']) ?></td>
                        <td data-rotulo="Situação">
                            <span class="etiqueta etiqueta-<?= e($cliente['status']) ?>"><?= e(Cliente::STATUS[$cliente['status']]) ?></span>
                        </td>
                        <td class="tabela-acoes">
                            <a href="<?= e(url('clientes/ver', ['id' => $cliente['id']])) ?>">Ver</a>
                            <a href="<?= e(url('clientes/editar', ['id' => $cliente['id']])) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= App\Core\View::renderizar('partials/paginacao', [
        'paginacao'  => $paginacao,
        'rota'       => 'clientes',
        'parametros' => $parametrosPaginacao,
    ]) ?>
<?php endif; ?>
