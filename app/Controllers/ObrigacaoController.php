<?php

declare(strict_types=1);

namespace App\Controllers;

defined('APP_ROOT') || exit;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Cliente;
use App\Models\Obrigacao;
use App\Models\TipoObrigacao;
use App\Models\Usuario;
use PDOException;

/**
 * Obrigações contábeis: CRUD, conclusão e cancelamento.
 *
 * Acesso (config/routes.php): admin e contador em tudo, exceto a exclusão
 * (somente admin). Para o contador, "cancelar" é a forma de retirar uma
 * obrigação do acompanhamento sem apagar o histórico.
 */
final class ObrigacaoController extends Controller
{
    private const POR_PAGINA = 10;
    private const VALOR_MAXIMO = 9999999999.99; // DECIMAL(12,2)

    private const ERRO_DUPLICADO = 1062;
    private const ERRO_VINCULO   = 1451;

    private Obrigacao $obrigacoes;

    public function __construct()
    {
        $this->obrigacoes = new Obrigacao();
    }

    /**
     * GET obrigacoes — listagem com pesquisa, filtros e paginação.
     */
    public function index(): void
    {
        $filtros   = $this->filtros();
        $restrito  = Auth::clienteRestrito();
        $paginacao = new Paginator(
            $this->obrigacoes->contar($filtros, $restrito),
            Paginator::paginaDaRequisicao(),
            self::POR_PAGINA
        );

        $this->view('obrigacoes/index', [
            'titulo'       => 'Obrigações',
            'obrigacoes'   => $this->obrigacoes->listar($filtros, $paginacao->porPagina, $paginacao->offset(), $restrito),
            'filtros'      => $filtros,
            'paginacao'    => $paginacao,
            'contagem'     => $this->obrigacoes->contarPorPrazo($restrito),
            'clientes'     => (new Cliente())->listarParaSelecao(true, null, $restrito),
            'tipos'        => (new TipoObrigacao())->listar(),
            'responsaveis' => (new Usuario())->listarResponsaveis(),
        ]);
    }

    /**
     * GET obrigacoes/ver&id=N
     */
    public function ver(): void
    {
        $obrigacao = $this->obrigacaoOu404($this->query('id'));

        $this->view('obrigacoes/ver', [
            'titulo'     => $obrigacao['tipo_nome'] . ' — ' . formatar_competencia($obrigacao['competencia']),
            'obrigacao'  => $obrigacao,
            'documentos' => $this->obrigacoes->contarDocumentos((int) $obrigacao['id']),
        ]);
    }

    /**
     * GET/POST obrigacoes/nova
     */
    public function nova(): void
    {
        if ($this->ehPost()) {
            $this->salvar(null);
            return;
        }

        $this->formulario(null, [
            'cliente_id'     => $this->query('cliente_id'),
            'competencia'    => date('Y-m', strtotime('first day of last month')),
            'status'         => 'pendente',
            'responsavel_id' => (string) Auth::id(),
        ]);
    }

    /**
     * GET/POST obrigacoes/editar&id=N
     */
    public function editar(): void
    {
        $obrigacao = $this->obrigacaoOu404($this->query('id'));

        if ($this->ehPost()) {
            $this->salvar($obrigacao);
            return;
        }

        $this->formulario($obrigacao, [
            'cliente_id'        => (string) $obrigacao['cliente_id'],
            'tipo_obrigacao_id' => (string) $obrigacao['tipo_obrigacao_id'],
            'competencia'       => substr($obrigacao['competencia'], 0, 7),
            'data_vencimento'   => $obrigacao['data_vencimento'],
            'status'            => $obrigacao['status'],
            'data_conclusao'    => (string) $obrigacao['data_conclusao'],
            'valor'             => $obrigacao['valor'] === null ? '' : number_format((float) $obrigacao['valor'], 2, ',', '.'),
            'responsavel_id'    => (string) $obrigacao['responsavel_id'],
            'observacoes'       => (string) $obrigacao['observacoes'],
        ]);
    }

    /**
     * POST obrigacoes/concluir — registra a conclusão com a data informada (padrão: hoje).
     */
    public function concluir(): void
    {
        $obrigacao = $this->obrigacaoOu404($this->post('id'));
        $id        = (int) $obrigacao['id'];

        if (!in_array($obrigacao['status'], Obrigacao::STATUS_ABERTOS, true)) {
            Session::flash('erro', 'Somente obrigações pendentes ou em andamento podem ser concluídas.');
            $this->redirecionar('obrigacoes/ver', ['id' => $id]);
        }

        $data = $this->normalizarData($this->post('data_conclusao')) ?: date('Y-m-d');
        $erro = $this->erroDataConclusao($data);

        if ($erro !== null) {
            Session::flash('erro', $erro);
            $this->redirecionar('obrigacoes/ver', ['id' => $id]);
        }

        $this->obrigacoes->alterarSituacao($id, 'concluida', $data);

        $mensagem = 'Obrigação concluída em ' . formatar_data($data) . '.';
        if ($data > $obrigacao['data_vencimento']) {
            $mensagem .= ' A conclusão foi registrada após o vencimento.';
        }
        Session::flash('sucesso', $mensagem);
        $this->redirecionar('obrigacoes/ver', ['id' => $id]);
    }

    /**
     * POST obrigacoes/cancelar — somente obrigações em aberto.
     * Uma obrigação concluída precisa ser reaberta (edição) antes de ser cancelada.
     */
    public function cancelar(): void
    {
        $obrigacao = $this->obrigacaoOu404($this->post('id'));
        $id        = (int) $obrigacao['id'];

        if (!in_array($obrigacao['status'], Obrigacao::STATUS_ABERTOS, true)) {
            Session::flash('erro', 'Somente obrigações pendentes ou em andamento podem ser canceladas.');
            $this->redirecionar('obrigacoes/ver', ['id' => $id]);
        }

        $this->obrigacoes->alterarSituacao($id, 'cancelada');

        Session::flash('sucesso', 'Obrigação cancelada. Ela deixa de aparecer como pendência.');
        $this->redirecionar('obrigacoes/ver', ['id' => $id]);
    }

    /**
     * POST obrigacoes/excluir (somente admin). Bloqueada se houver documentos vinculados.
     */
    public function excluir(): void
    {
        $obrigacao = $this->obrigacaoOu404($this->post('id'));
        $id        = (int) $obrigacao['id'];

        $documentos = $this->obrigacoes->contarDocumentos($id);
        if ($documentos > 0) {
            Session::flash('erro', $this->mensagemDocumentos($documentos));
            $this->redirecionar('obrigacoes/ver', ['id' => $id]);
        }

        try {
            $this->obrigacoes->excluir($id);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== self::ERRO_VINCULO) {
                throw $e;
            }
            Session::flash('erro', $this->mensagemDocumentos($this->obrigacoes->contarDocumentos($id)));
            $this->redirecionar('obrigacoes/ver', ['id' => $id]);
        }

        Session::flash('sucesso', "Obrigação {$obrigacao['tipo_nome']} de "
            . formatar_competencia($obrigacao['competencia']) . " ({$obrigacao['cliente_nome']}) excluída.");
        $this->redirecionar('obrigacoes');
    }

    // ------------------------------------------------------------------

    private function salvar(?array $obrigacao): void
    {
        $idAtual      = $obrigacao !== null ? (int) $obrigacao['id'] : null;
        $dados        = $this->dadosDoFormulario();
        $tipos        = (new TipoObrigacao())->listar();
        $responsaveis = (new Usuario())->listarResponsaveis(
            $obrigacao !== null ? (int) $obrigacao['responsavel_id'] : null
        );

        $validador = new Validator($dados);
        $validador->validar([
            'cliente_id'        => ['obrigatorio', 'inteiro'],
            'tipo_obrigacao_id' => ['obrigatorio', 'in:' . implode(',', array_column($tipos, 'id'))],
            'competencia'       => ['obrigatorio', 'mes'],
            'data_vencimento'   => ['obrigatorio', 'data'],
            'status'            => ['obrigatorio', 'in:' . implode(',', array_keys(Obrigacao::STATUS))],
            'data_conclusao'    => $dados['status'] === 'concluida' ? ['obrigatorio', 'data'] : [],
            'valor'             => ['dinheiro'],
            'responsavel_id'    => ['obrigatorio', 'in:' . implode(',', array_column($responsaveis, 'id'))],
            'observacoes'       => ['max:2000'],
        ]);

        $this->validarCliente($validador, $dados, $obrigacao);
        $this->validarRegrasDeNegocio($validador, $dados);

        if (!$validador->temErro('cliente_id') && !$validador->temErro('tipo_obrigacao_id') && !$validador->temErro('competencia')
            && $this->obrigacoes->existeDuplicada((int) $dados['cliente_id'], (int) $dados['tipo_obrigacao_id'], $dados['competencia'] . '-01', $idAtual)
        ) {
            $validador->adicionarErro('competencia', $this->mensagemDuplicada());
        }

        if ($validador->erros() === []) {
            try {
                if ($idAtual === null) {
                    $idAtual = $this->obrigacoes->criar($this->paraGravar($dados));
                    Session::flash('sucesso', 'Obrigação cadastrada com sucesso.');
                } else {
                    $this->obrigacoes->atualizar($idAtual, $this->paraGravar($dados));
                    Session::flash('sucesso', 'Obrigação atualizada.');
                }

                $this->redirecionar('obrigacoes/ver', ['id' => $idAtual]);
            } catch (PDOException $e) {
                // Garantias extras para alterações simultâneas.
                match ($e->errorInfo[1] ?? null) {
                    self::ERRO_DUPLICADO => $validador->adicionarErro('competencia', $this->mensagemDuplicada()),
                    self::ERRO_VINCULO   => $validador->adicionarErro('cliente_id', 'O cliente não pode ser alterado porque há documentos vinculados a esta obrigação.'),
                    default              => throw $e,
                };
            }
        }

        http_response_code(422);
        $this->formulario($obrigacao, $dados, $validador->erros(), $tipos, $responsaveis);
    }

    /**
     * O cliente precisa existir, estar ativo (exceto se já era o cliente da
     * obrigação) e não pode ser trocado quando há documentos vinculados.
     */
    private function validarCliente(Validator $validador, array $dados, ?array $obrigacao): void
    {
        if ($validador->temErro('cliente_id')) {
            return;
        }

        $clienteId    = (int) $dados['cliente_id'];
        $mesmoCliente = $obrigacao !== null && (int) $obrigacao['cliente_id'] === $clienteId;
        $cliente      = (new Cliente())->buscarPorId($clienteId, Auth::clienteRestrito());

        if ($cliente === null) {
            $validador->adicionarErro('cliente_id', 'Cliente não encontrado. Selecione um cliente da lista.');
        } elseif ($cliente['status'] !== 'ativo' && !$mesmoCliente) {
            $validador->adicionarErro('cliente_id', 'Este cliente está inativo. Selecione um cliente ativo.');
        } elseif ($obrigacao !== null && !$mesmoCliente) {
            $documentos = $this->obrigacoes->contarDocumentos((int) $obrigacao['id']);
            if ($documentos > 0) {
                $validador->adicionarErro('cliente_id', 'O cliente não pode ser alterado: esta obrigação tem '
                    . plural($documentos, 'documento vinculado', 'documentos vinculados') . ' ao cliente atual.');
            }
        }
    }

    /**
     * Regras que envolvem mais de um campo.
     */
    private function validarRegrasDeNegocio(Validator $validador, array $dados): void
    {
        if (!$validador->temErro('competencia') && !$validador->temErro('data_vencimento')
            && $dados['data_vencimento'] < $dados['competencia'] . '-01'
        ) {
            $validador->adicionarErro('data_vencimento', 'O vencimento não pode ser anterior ao início da competência ('
                . formatar_competencia($dados['competencia']) . ').');
        }

        // Situação x data de conclusão (mesma regra do CHECK do banco).
        if (!$validador->temErro('status')) {
            if ($dados['status'] === 'concluida') {
                if (!$validador->temErro('data_conclusao')) {
                    $erro = $this->erroDataConclusao($dados['data_conclusao']);
                    if ($erro !== null) {
                        $validador->adicionarErro('data_conclusao', $erro);
                    }
                }
            } elseif ($dados['data_conclusao'] !== '') {
                $validador->adicionarErro('data_conclusao', 'A data de conclusão só pode ser informada quando a situação for "Concluída".');
            }
        }

        if (!$validador->temErro('valor') && $dados['valor'] !== ''
            && (float) $this->decimalParaBanco($dados['valor']) > self::VALOR_MAXIMO
        ) {
            $validador->adicionarErro('valor', 'Valor acima do limite permitido.');
        }
    }

    private function erroDataConclusao(string $data): ?string
    {
        if (!Validator::dataValida($data, 'Y-m-d')) {
            return 'Informe uma data de conclusão válida.';
        }

        return $data > date('Y-m-d') ? 'A data de conclusão não pode ser uma data futura.' : null;
    }

    /**
     * @return array<string, string>
     */
    private function dadosDoFormulario(): array
    {
        return [
            'cliente_id'        => $this->post('cliente_id'),
            'tipo_obrigacao_id' => $this->post('tipo_obrigacao_id'),
            'competencia'       => $this->normalizarCompetencia($this->post('competencia')),
            'data_vencimento'   => $this->normalizarData($this->post('data_vencimento')),
            'status'            => $this->post('status'),
            'data_conclusao'    => $this->normalizarData($this->post('data_conclusao')),
            'valor'             => str_replace(['R$', ' '], '', $this->post('valor')),
            'responsavel_id'    => $this->post('responsavel_id'),
            'observacoes'       => $this->post('observacoes'),
        ];
    }

    private function paraGravar(array $dados): array
    {
        return [
            'cliente_id'        => (int) $dados['cliente_id'],
            'tipo_obrigacao_id' => (int) $dados['tipo_obrigacao_id'],
            'competencia'       => $dados['competencia'] . '-01',
            'data_vencimento'   => $dados['data_vencimento'],
            'status'            => $dados['status'],
            'data_conclusao'    => $dados['status'] === 'concluida' ? $dados['data_conclusao'] : null,
            'valor'             => $dados['valor'] === '' ? null : $this->decimalParaBanco($dados['valor']),
            'responsavel_id'    => (int) $dados['responsavel_id'],
            'observacoes'       => $dados['observacoes'] === '' ? null : $dados['observacoes'],
        ];
    }

    /**
     * Filtros da listagem, aceitando somente valores válidos.
     */
    private function filtros(): array
    {
        $opcao   = static fn (string $v, array $permitidos): string => in_array($v, $permitidos, true) ? $v : '';
        $inteiro = static fn (string $v): int => ($i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false ? 0 : $i;
        $data    = fn (string $v): string => Validator::dataValida($d = $this->normalizarData($v), 'Y-m-d') ? $d : '';
        $mes     = $this->normalizarCompetencia($this->query('competencia'));

        return [
            'busca'             => mb_substr($this->query('busca'), 0, 100),
            'cliente_id'        => $inteiro($this->query('cliente_id')),
            'tipo_obrigacao_id' => $inteiro($this->query('tipo_obrigacao_id')),
            'status'            => $opcao($this->query('status'), array_keys(Obrigacao::STATUS)),
            'prazo'             => $opcao($this->query('prazo'), array_keys(Obrigacao::PRAZOS)),
            'competencia'       => Validator::dataValida($mes, 'Y-m') ? $mes : '',
            'vencimento_de'     => $data($this->query('vencimento_de')),
            'vencimento_ate'    => $data($this->query('vencimento_ate')),
            'responsavel_id'    => $inteiro($this->query('responsavel_id')),
            'ordenar'           => $opcao($this->query('ordenar'), array_keys(Obrigacao::ORDENACOES)) ?: 'prioridade',
        ];
    }

    private function formulario(
        ?array $obrigacao,
        array $valores,
        array $erros = [],
        ?array $tipos = null,
        ?array $responsaveis = null
    ): void {
        $clienteAtual = $obrigacao !== null ? (int) $obrigacao['cliente_id'] : null;

        $this->view('obrigacoes/form', [
            'titulo'       => $obrigacao === null ? 'Nova obrigação' : 'Editar obrigação',
            'obrigacao'    => $obrigacao,
            'valores'      => $valores,
            'erros'        => $erros,
            'clientes'     => (new Cliente())->listarParaSelecao(false, $clienteAtual, Auth::clienteRestrito()),
            'tipos'        => $tipos ?? (new TipoObrigacao())->listar(),
            'responsaveis' => $responsaveis ?? (new Usuario())->listarResponsaveis(
                $obrigacao !== null ? (int) $obrigacao['responsavel_id'] : null
            ),
            'documentos'   => $obrigacao !== null ? $this->obrigacoes->contarDocumentos((int) $obrigacao['id']) : 0,
        ]);
    }

    private function obrigacaoOu404(string $id): array
    {
        $obrigacao = $this->obrigacoes->buscarPorId($this->id($id), Auth::clienteRestrito());

        if ($obrigacao === null) {
            throw new HttpException(404, 'Obrigação não encontrada.');
        }

        return $obrigacao;
    }

    /**
     * Aceita AAAA-MM (campo "month") ou MM/AAAA (digitado).
     */
    private function normalizarCompetencia(string $valor): string
    {
        return preg_match('#^(\d{2})/(\d{4})$#', $valor, $m) ? "{$m[2]}-{$m[1]}" : $valor;
    }

    /**
     * Aceita AAAA-MM-DD (campo "date") ou DD/MM/AAAA (digitado).
     */
    private function normalizarData(string $valor): string
    {
        return preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valor, $m) ? "{$m[3]}-{$m[2]}-{$m[1]}" : $valor;
    }

    /**
     * "1.234,56" / "1234,56" / "1234.56" -> "1234.56"
     */
    private function decimalParaBanco(string $valor): string
    {
        return str_contains($valor, ',') ? str_replace(['.', ','], ['', '.'], $valor) : $valor;
    }

    private function mensagemDuplicada(): string
    {
        return 'Já existe uma obrigação deste tipo para este cliente nesta competência.';
    }

    private function mensagemDocumentos(int $documentos): string
    {
        return 'Esta obrigação não pode ser excluída porque possui '
            . plural($documentos, 'documento vinculado', 'documentos vinculados')
            . '. Nenhum registro foi apagado. Se ela não for mais necessária, use "Cancelar obrigação".';
    }
}
