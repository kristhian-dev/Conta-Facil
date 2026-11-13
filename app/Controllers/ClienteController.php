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
use App\Models\Usuario;
use PDOException;

/**
 * CRUD de clientes.
 *
 * Acesso (config/routes.php): admin e contador em todas as ações,
 * exceto a exclusão, que é exclusiva do admin.
 */
final class ClienteController extends Controller
{
    private const POR_PAGINA = 10;

    /** Códigos de erro do MySQL tratados com mensagem amigável. */
    private const ERRO_DUPLICADO = 1062;
    private const ERRO_VINCULO   = 1451;

    private Cliente $clientes;

    public function __construct()
    {
        $this->clientes = new Cliente();
    }

    /**
     * GET clientes — listagem com pesquisa, filtros e paginação.
     */
    public function index(): void
    {
        $filtros   = $this->filtros();
        $restrito  = Auth::clienteRestrito();
        $paginacao = new Paginator(
            $this->clientes->contar($filtros, $restrito),
            Paginator::paginaDaRequisicao(),
            self::POR_PAGINA
        );

        $this->view('clientes/index', [
            'titulo'       => 'Clientes',
            'clientes'     => $this->clientes->listar($filtros, $paginacao->porPagina, $paginacao->offset(), $restrito),
            'filtros'      => $filtros,
            'paginacao'    => $paginacao,
            'responsaveis' => (new Usuario())->listarResponsaveis(),
        ]);
    }

    /**
     * GET clientes/ver&id=N
     */
    public function ver(): void
    {
        $cliente = $this->clienteOu404($this->query('id'));

        $this->view('clientes/ver', [
            'titulo'   => $cliente['razao_social'],
            'cliente'  => $cliente,
            'vinculos' => $this->clientes->contarVinculos((int) $cliente['id']),
        ]);
    }

    /**
     * GET  clientes/novo -> formulário vazio
     * POST clientes/novo -> valida e cadastra
     */
    public function novo(): void
    {
        if ($this->ehPost()) {
            $this->salvar(null);
            return;
        }

        $this->formulario(null, [
            'tipo_pessoa'    => 'PJ',
            'status'         => 'ativo',
            'responsavel_id' => (string) Auth::id(),
        ]);
    }

    /**
     * GET  clientes/editar&id=N -> formulário preenchido
     * POST clientes/editar&id=N -> valida e atualiza
     */
    public function editar(): void
    {
        $cliente = $this->clienteOu404($this->query('id'));

        if ($this->ehPost()) {
            $this->salvar($cliente);
            return;
        }

        $this->formulario($cliente, array_map(
            static fn (mixed $valor): string => (string) $valor,
            $cliente
        ));
    }

    /**
     * POST clientes/excluir (somente admin; CSRF validado pelo Router).
     * Clientes com obrigações, documentos ou usuários vinculados não são
     * excluídos: nada relacionado é apagado.
     */
    public function excluir(): void
    {
        $cliente = $this->clienteOu404($this->post('id'));
        $id      = (int) $cliente['id'];

        $vinculos = $this->clientes->contarVinculos($id);
        if (array_sum($vinculos) > 0) {
            Session::flash('erro', $this->mensagemVinculos($vinculos));
            $this->redirecionar('clientes/ver', ['id' => $id]);
        }

        try {
            $this->clientes->excluir($id);
        } catch (PDOException $e) {
            // Garantia extra: um vínculo criado entre a verificação e a exclusão.
            if (($e->errorInfo[1] ?? null) !== self::ERRO_VINCULO) {
                throw $e;
            }
            Session::flash('erro', $this->mensagemVinculos($this->clientes->contarVinculos($id)));
            $this->redirecionar('clientes/ver', ['id' => $id]);
        }

        Session::flash('sucesso', "Cliente \"{$cliente['razao_social']}\" excluído.");
        $this->redirecionar('clientes');
    }

    // ------------------------------------------------------------------

    private function salvar(?array $cliente): void
    {
        $idAtual      = $cliente !== null ? (int) $cliente['id'] : null;
        $dados        = $this->dadosDoFormulario();
        $responsaveis = (new Usuario())->listarResponsaveis(
            $cliente !== null ? (int) $cliente['responsavel_id'] : null
        );

        $validador = new Validator($dados);
        $validador->validar($this->regras($dados['tipo_pessoa'], array_column($responsaveis, 'id')));

        if (!$validador->temErro('cpf_cnpj') && $this->clientes->cpfCnpjEmUso($dados['cpf_cnpj'], $idAtual)) {
            $validador->adicionarErro('cpf_cnpj', $this->mensagemDuplicado($dados['tipo_pessoa']));
        }

        if ($validador->erros() === []) {
            try {
                if ($idAtual === null) {
                    $idAtual = $this->clientes->criar($this->paraGravar($dados));
                    Session::flash('sucesso', 'Cliente cadastrado com sucesso.');
                } else {
                    $this->clientes->atualizar($idAtual, $this->paraGravar($dados));
                    Session::flash('sucesso', 'Dados do cliente atualizados.');
                }

                $this->redirecionar('clientes/ver', ['id' => $idAtual]);
            } catch (PDOException $e) {
                // Garantia extra: cadastro simultâneo do mesmo CPF/CNPJ.
                if (($e->errorInfo[1] ?? null) !== self::ERRO_DUPLICADO) {
                    throw $e;
                }
                $validador->adicionarErro('cpf_cnpj', $this->mensagemDuplicado($dados['tipo_pessoa']));
            }
        }

        http_response_code(422);
        $this->formulario($cliente, $dados, $validador->erros(), $responsaveis);
    }

    /**
     * Lê e normaliza os campos do formulário antes da validação.
     *
     * @return array<string, string>
     */
    private function dadosDoFormulario(): array
    {
        $tipo = $this->post('tipo_pessoa');
        $pf   = $tipo === 'PF';

        return [
            'tipo_pessoa'       => $tipo,
            'razao_social'      => (string) preg_replace('/\s+/u', ' ', $this->post('razao_social')),
            // Pessoa física não tem nome fantasia e sempre usa o regime "Pessoa Física".
            'nome_fantasia'     => $pf ? '' : (string) preg_replace('/\s+/u', ' ', $this->post('nome_fantasia')),
            // Remove apenas a máscara; letras continuam para que a validação as recuse.
            'cpf_cnpj'          => (string) preg_replace('/[\s.\/-]/', '', $this->post('cpf_cnpj')),
            'regime_tributario' => $pf ? Cliente::REGIME_PF : $this->post('regime_tributario'),
            'email'             => mb_strtolower($this->post('email')),
            'telefone'          => $this->post('telefone'),
            'responsavel_id'    => $this->post('responsavel_id'),
            'status'            => $this->post('status'),
            'observacoes'       => $this->post('observacoes'),
        ];
    }

    /**
     * @param int[] $idsResponsaveis
     * @return array<string, string[]>
     */
    private function regras(string $tipo, array $idsResponsaveis): array
    {
        $regrasDocumento = ['obrigatorio'];
        if ($tipo === 'PF') {
            $regrasDocumento[] = 'cpf';
        } elseif ($tipo === 'PJ') {
            $regrasDocumento[] = 'cnpj';
        }

        $regimes = $tipo === 'PF' ? [Cliente::REGIME_PF] : Cliente::REGIMES_PJ;

        return [
            'tipo_pessoa'       => ['obrigatorio', 'in:' . implode(',', array_keys(Cliente::TIPOS))],
            'razao_social'      => ['obrigatorio', 'min:3', 'max:160'],
            'nome_fantasia'     => ['max:160'],
            'cpf_cnpj'          => $regrasDocumento,
            'regime_tributario' => ['obrigatorio', 'in:' . implode(',', $regimes)],
            'email'             => ['email', 'max:160'],
            'telefone'          => ['telefone'],
            'responsavel_id'    => ['obrigatorio', 'in:' . implode(',', $idsResponsaveis)],
            'status'            => ['obrigatorio', 'in:' . implode(',', array_keys(Cliente::STATUS))],
            'observacoes'       => ['max:2000'],
        ];
    }

    /**
     * Converte os dados validados para gravação (vazios opcionais viram NULL).
     */
    private function paraGravar(array $dados): array
    {
        $opcional = static fn (string $valor): ?string => $valor === '' ? null : $valor;

        return [
            'razao_social'      => $dados['razao_social'],
            'nome_fantasia'     => $opcional($dados['nome_fantasia']),
            'tipo_pessoa'       => $dados['tipo_pessoa'],
            'cpf_cnpj'          => $dados['cpf_cnpj'],
            'regime_tributario' => $dados['regime_tributario'],
            'email'             => $opcional($dados['email']),
            'telefone'          => $opcional(formatar_telefone($dados['telefone'])),
            'responsavel_id'    => (int) $dados['responsavel_id'],
            'status'            => $dados['status'],
            'observacoes'       => $opcional($dados['observacoes']),
        ];
    }

    /**
     * Filtros da listagem, aceitando somente valores conhecidos.
     */
    private function filtros(): array
    {
        $opcao = static fn (string $valor, array $permitidos): string => in_array($valor, $permitidos, true) ? $valor : '';
        $responsavel = filter_var($this->query('responsavel_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'busca'             => mb_substr($this->query('busca'), 0, 100),
            'tipo_pessoa'       => $opcao($this->query('tipo_pessoa'), array_keys(Cliente::TIPOS)),
            'regime_tributario' => $opcao($this->query('regime_tributario'), [...Cliente::REGIMES_PJ, Cliente::REGIME_PF]),
            'status'            => $opcao($this->query('status'), array_keys(Cliente::STATUS)),
            'responsavel_id'    => $responsavel === false ? 0 : $responsavel,
            'ordenar'           => $opcao($this->query('ordenar'), array_keys(Cliente::ORDENACOES)) ?: 'nome',
        ];
    }

    private function formulario(?array $cliente, array $valores, array $erros = [], ?array $responsaveis = null): void
    {
        $this->view('clientes/form', [
            'titulo'       => $cliente === null ? 'Novo cliente' : 'Editar cliente',
            'cliente'      => $cliente,
            'valores'      => $valores,
            'erros'        => $erros,
            'responsaveis' => $responsaveis ?? (new Usuario())->listarResponsaveis(
                $cliente !== null ? (int) $cliente['responsavel_id'] : null
            ),
        ]);
    }

    private function clienteOu404(string $id): array
    {
        $cliente = $this->clientes->buscarPorId($this->id($id), Auth::clienteRestrito());

        if ($cliente === null) {
            throw new HttpException(404, 'Cliente não encontrado.');
        }

        return $cliente;
    }

    private function mensagemDuplicado(string $tipo): string
    {
        return ($tipo === 'PF' ? 'Este CPF' : 'Este CNPJ') . ' já está cadastrado para outro cliente.';
    }

    /**
     * @param array{obrigacoes: int, documentos: int, usuarios: int} $vinculos
     */
    private function mensagemVinculos(array $vinculos): string
    {
        $partes = array_filter([
            plural($vinculos['obrigacoes'], 'obrigação', 'obrigações'),
            plural($vinculos['documentos'], 'documento', 'documentos'),
            plural($vinculos['usuarios'], 'usuário de acesso', 'usuários de acesso'),
        ]);

        return 'Este cliente não pode ser excluído porque possui ' . implode(', ', $partes)
            . ' vinculado(s). Nenhum registro foi apagado. Para retirá-lo do acompanhamento, '
            . 'edite o cliente e altere a situação para "Inativo".';
    }
}
