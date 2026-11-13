<?php

declare(strict_types=1);

namespace App\Controllers;

defined('APP_ROOT') || exit;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Session;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\Obrigacao;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Documentos: solicitação, recebimento (com upload), análise e entrega segura do arquivo.
 *
 * Acesso (config/routes.php): admin e contador em tudo, exceto a exclusão (somente admin).
 */
final class DocumentoController extends Controller
{
    private const POR_PAGINA = 10;
    private const ERRO_FK    = 1452;

    private Documento $documentos;

    public function __construct()
    {
        $this->documentos = new Documento();
    }

    /**
     * GET documentos — listagem com pesquisa, filtros e paginação.
     */
    public function index(): void
    {
        $filtros   = $this->filtros();
        $restrito  = Auth::clienteRestrito();
        $paginacao = new Paginator(
            $this->documentos->contar($filtros, $restrito),
            Paginator::paginaDaRequisicao(),
            self::POR_PAGINA
        );

        $this->view('documentos/index', [
            'titulo'     => 'Documentos',
            'documentos' => $this->documentos->listar($filtros, $paginacao->porPagina, $paginacao->offset(), $restrito),
            'filtros'    => $filtros,
            'paginacao'  => $paginacao,
            'contagem'   => $this->documentos->contarPendencias($restrito),
            'clientes'   => (new Cliente())->listarParaSelecao(true, null, $restrito),
            'obrigacoes' => $this->documentos->listarObrigacoesComDocumentos($restrito),
        ]);
    }

    /**
     * GET documentos/ver&id=N
     */
    public function ver(): void
    {
        $this->exibirFicha($this->documentoOu404($this->query('id')));
    }

    /**
     * GET/POST documentos/novo — solicitação de documento ao cliente.
     * Aceita ?obrigacao_id= ou ?cliente_id= para pré-selecionar.
     */
    public function novo(): void
    {
        if ($this->ehPost()) {
            $this->salvarSolicitacao();
            return;
        }

        $valores = ['cliente_id' => $this->query('cliente_id'), 'obrigacao_id' => $this->query('obrigacao_id')];

        if ($valores['obrigacao_id'] !== '' && ctype_digit($valores['obrigacao_id'])) {
            $obrigacao = (new Obrigacao())->buscarPorId((int) $valores['obrigacao_id'], Auth::clienteRestrito());
            $valores['cliente_id'] = $obrigacao !== null ? (string) $obrigacao['cliente_id'] : $valores['cliente_id'];
        }

        $this->formulario(null, $valores);
    }

    /**
     * GET/POST documentos/editar&id=N — título, descrição e prazo.
     * Cliente e obrigação não podem ser alterados depois da solicitação.
     */
    public function editar(): void
    {
        $documento = $this->documentoOu404($this->query('id'));

        if (!$this->ehPost()) {
            $this->formulario($documento, [
                'titulo'      => $documento['titulo'],
                'descricao'   => (string) $documento['descricao'],
                'prazo_envio' => (string) $documento['prazo_envio'],
            ]);
            return;
        }

        $dados = [
            'titulo'      => $this->post('titulo'),
            'descricao'   => $this->post('descricao'),
            'prazo_envio' => $this->post('prazo_envio'),
        ];
        $validador = $this->validarDadosDescritivos($dados, (string) $documento['prazo_envio']);

        if ($validador->erros() !== []) {
            http_response_code(422);
            $this->formulario($documento, $dados, $validador->erros());
            return;
        }

        $this->documentos->atualizarDados(
            (int) $documento['id'],
            $dados['titulo'],
            $dados['descricao'] === '' ? null : $dados['descricao'],
            $dados['prazo_envio'] === '' ? null : $dados['prazo_envio']
        );

        Session::flash('sucesso', 'Dados do documento atualizados.');
        $this->redirecionar('documentos/ver', ['id' => $documento['id']]);
    }

    /**
     * GET/POST documentos/receber&id=N — registra o recebimento com o arquivo.
     * Permitido para documentos solicitados ou rejeitados (reenvio).
     */
    public function receber(): void
    {
        $documento = $this->documentoOu404($this->query('id'));
        $id        = (int) $documento['id'];

        if (!in_array($documento['status'], Documento::NAO_ENVIADOS, true)) {
            Session::flash('erro', 'Este documento já foi recebido. Só é possível registrar um novo recebimento após uma rejeição.');
            $this->redirecionar('documentos/ver', ['id' => $id]);
        }

        if (!$this->ehPost()) {
            $this->formularioRecebimento($documento, ['recebido_em' => date('Y-m-d\TH:i')]);
            return;
        }

        $dados = [
            'canal_recebimento' => $this->post('canal_recebimento'),
            'recebido_em'       => $this->post('recebido_em'),
        ];

        $validador = new Validator($dados);
        $validador->validar([
            'canal_recebimento' => ['obrigatorio', 'in:' . implode(',', array_keys(Documento::CANAIS))],
            'recebido_em'       => ['obrigatorio', 'datahora'],
        ]);

        if (!$validador->temErro('canal_recebimento') && !in_array($dados['canal_recebimento'], Documento::CANAIS_MANUAIS, true)) {
            $validador->adicionarErro('canal_recebimento', 'O canal "Sistema" será usado pelo portal do cliente (versão 2). Selecione e-mail, WhatsApp ou presencial.');
        }
        if (!$validador->temErro('recebido_em') && $dados['recebido_em'] > date('Y-m-d\TH:i')) {
            $validador->adicionarErro('recebido_em', 'A data de recebimento não pode estar no futuro.');
        }

        $arquivo = Upload::validar($_FILES['arquivo'] ?? null);
        if ($arquivo['erro'] !== null) {
            $validador->adicionarErro('arquivo', $arquivo['erro']);
        }

        if ($validador->erros() !== []) {
            http_response_code(422);
            $this->formularioRecebimento($documento, $dados, $validador->erros());
            return;
        }

        try {
            $nomeArmazenado = Upload::armazenar($arquivo);
        } catch (RuntimeException $e) {
            error_log('[ContaFácil] Upload: ' . $e->getMessage());
            http_response_code(500);
            $this->formularioRecebimento($documento, $dados, [
                'arquivo' => 'Não foi possível salvar o arquivo no servidor. Avise o administrador do sistema.',
            ]);
            return;
        }

        // Se a gravação no banco falhar, o arquivo recém-salvo é removido (sem órfãos).
        try {
            $gravado = $this->documentos->registrarRecebimento(
                $id,
                $dados['canal_recebimento'],
                $arquivo['nome_original'],
                $nomeArmazenado,
                $arquivo['mime'],
                $arquivo['tamanho'],
                (int) Auth::id(),
                str_replace('T', ' ', $dados['recebido_em']) . ':00'
            );
        } catch (Throwable $e) {
            Upload::remover($nomeArmazenado);
            throw $e;
        }

        if (!$gravado) {
            Upload::remover($nomeArmazenado);
            Session::flash('erro', 'O documento foi alterado por outra pessoa enquanto você registrava o recebimento. Confira a situação atual.');
            $this->redirecionar('documentos/ver', ['id' => $id]);
        }

        // Reenvio após rejeição: o arquivo anterior deixa de ser referenciado.
        if ($documento['arquivo_armazenado'] !== null) {
            Upload::remover($documento['arquivo_armazenado']);
        }

        Session::flash('sucesso', 'Recebimento registrado. O documento está aguardando análise.');
        $this->redirecionar('documentos/ver', ['id' => $id]);
    }

    /**
     * POST documentos/aprovar
     */
    public function aprovar(): void
    {
        $documento = $this->documentoOu404($this->post('id'));
        $id        = (int) $documento['id'];

        if ($documento['status'] !== 'recebido') {
            Session::flash('erro', 'Somente documentos recebidos (aguardando análise) podem ser aprovados.');
        } elseif (!$this->documentos->aprovar($id, (int) Auth::id())) {
            Session::flash('erro', 'O documento foi alterado por outra pessoa. Confira a situação atual.');
        } else {
            Session::flash('sucesso', 'Documento aprovado.');
        }

        $this->redirecionar('documentos/ver', ['id' => $id]);
    }

    /**
     * POST documentos/rejeitar — exige justificativa; arquivo e recebimento são preservados.
     */
    public function rejeitar(): void
    {
        $documento = $this->documentoOu404($this->post('id'));
        $id        = (int) $documento['id'];

        if ($documento['status'] !== 'recebido') {
            Session::flash('erro', 'Somente documentos recebidos (aguardando análise) podem ser rejeitados.');
            $this->redirecionar('documentos/ver', ['id' => $id]);
        }

        $dados = ['motivo_rejeicao' => $this->post('motivo_rejeicao')];
        $validador = new Validator($dados);

        if (!$validador->validar(['motivo_rejeicao' => ['obrigatorio', 'min:5', 'max:2000']])) {
            http_response_code(422);
            $this->exibirFicha($documento, $validador->erros(), $dados['motivo_rejeicao']);
            return;
        }

        if (!$this->documentos->rejeitar($id, (int) Auth::id(), $dados['motivo_rejeicao'])) {
            Session::flash('erro', 'O documento foi alterado por outra pessoa. Confira a situação atual.');
        } else {
            Session::flash('sucesso', 'Documento rejeitado. Ele volta a aparecer como pendente de envio pelo cliente.');
        }

        $this->redirecionar('documentos/ver', ['id' => $id]);
    }

    /**
     * GET documentos/arquivo&id=N[&modo=ver]
     * Única forma de acessar um arquivo: exige login, perfil e (no futuro) o
     * escopo do cliente. O caminho nunca vem da requisição, só do banco.
     */
    public function arquivo(): void
    {
        $documento = $this->documentoOu404($this->query('id'));
        $caminho   = Upload::caminho($documento['arquivo_armazenado']);

        if ($caminho === null) {
            throw new HttpException(404, $documento['arquivo_armazenado'] === null
                ? 'Este documento ainda não possui arquivo.'
                : 'O arquivo deste documento não foi encontrado no servidor.');
        }

        $mime     = Upload::mimeParaEntrega($documento['arquivo_armazenado']);
        $exibivel = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
        $inline   = $exibivel && $this->query('modo') === 'ver';
        $original = (string) ($documento['arquivo_original'] ?? basename($caminho));
        $ascii    = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $original);

        // Libera a sessão antes de enviar o arquivo (não bloqueia outras abas).
        session_write_close();

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($caminho));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($original));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($caminho);
    }

    /**
     * POST documentos/excluir (somente admin). Remove o registro e o arquivo.
     */
    public function excluir(): void
    {
        $documento = $this->documentoOu404($this->post('id'));

        $this->documentos->excluir((int) $documento['id']);
        // O arquivo só é removido depois que o registro foi excluído com sucesso.
        Upload::remover($documento['arquivo_armazenado']);

        Session::flash('sucesso', "Documento \"{$documento['titulo']}\" excluído.");
        $this->redirecionar('documentos');
    }

    // ------------------------------------------------------------------

    private function salvarSolicitacao(): void
    {
        $dados = [
            'cliente_id'   => $this->post('cliente_id'),
            'obrigacao_id' => $this->post('obrigacao_id'),
            'titulo'       => (string) preg_replace('/\s+/u', ' ', $this->post('titulo')),
            'descricao'    => $this->post('descricao'),
            'prazo_envio'  => $this->post('prazo_envio'),
        ];

        $erros = $this->validarDadosDescritivos($dados, null)->erros();

        $complementar = new Validator($dados);
        $complementar->validar([
            'cliente_id'   => ['obrigatorio', 'inteiro'],
            'obrigacao_id' => ['inteiro'],
        ]);
        $erros += $complementar->erros();

        $restrito = Auth::clienteRestrito();

        if (!isset($erros['cliente_id'])) {
            $cliente = (new Cliente())->buscarPorId((int) $dados['cliente_id'], $restrito);
            if ($cliente === null) {
                $erros['cliente_id'] = 'Cliente não encontrado. Selecione um cliente da lista.';
            } elseif ($cliente['status'] !== 'ativo') {
                $erros['cliente_id'] = 'Este cliente está inativo. Selecione um cliente ativo.';
            }
        }

        if (!isset($erros['obrigacao_id']) && $dados['obrigacao_id'] !== '') {
            $obrigacao = (new Obrigacao())->buscarPorId((int) $dados['obrigacao_id'], $restrito);
            if ($obrigacao === null) {
                $erros['obrigacao_id'] = 'Obrigação não encontrada.';
            } elseif (!isset($erros['cliente_id']) && (int) $obrigacao['cliente_id'] !== (int) $dados['cliente_id']) {
                $erros['obrigacao_id'] = 'A obrigação selecionada pertence a outro cliente.';
            } elseif ($obrigacao['status'] === 'cancelada') {
                $erros['obrigacao_id'] = 'Não é possível vincular documentos a uma obrigação cancelada.';
            }
        }

        if ($erros === []) {
            try {
                $id = $this->documentos->solicitar(
                    (int) $dados['cliente_id'],
                    $dados['obrigacao_id'] === '' ? null : (int) $dados['obrigacao_id'],
                    $dados['titulo'],
                    $dados['descricao'] === '' ? null : $dados['descricao'],
                    $dados['prazo_envio'] === '' ? null : $dados['prazo_envio']
                );

                Session::flash('sucesso', 'Documento solicitado. Ele aparece como aguardando recebimento.');
                $this->redirecionar('documentos/ver', ['id' => $id]);
            } catch (PDOException $e) {
                // Garantia extra: a FK composta recusa obrigação de outro cliente.
                if (($e->errorInfo[1] ?? null) !== self::ERRO_FK) {
                    throw $e;
                }
                $erros['obrigacao_id'] = 'A obrigação selecionada não pertence a este cliente.';
            }
        }

        http_response_code(422);
        $this->formulario(null, $dados, $erros);
    }

    /**
     * Título, descrição e prazo (usados na solicitação e na edição).
     * O prazo não pode ficar no passado, exceto se for o valor já gravado.
     */
    private function validarDadosDescritivos(array $dados, ?string $prazoAtual): Validator
    {
        $validador = new Validator($dados);
        $validador->validar([
            'titulo'      => ['obrigatorio', 'min:3', 'max:160'],
            'descricao'   => ['max:2000'],
            'prazo_envio' => ['data'],
        ]);

        if (!$validador->temErro('prazo_envio') && $dados['prazo_envio'] !== ''
            && $dados['prazo_envio'] !== $prazoAtual && $dados['prazo_envio'] < date('Y-m-d')
        ) {
            $validador->adicionarErro('prazo_envio', 'O prazo de envio não pode estar no passado.');
        }

        return $validador;
    }

    private function filtros(): array
    {
        $opcao   = static fn (string $v, array $permitidos): string => in_array($v, $permitidos, true) ? $v : '';
        $inteiro = static fn (string $v): int => ($i = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) === false ? 0 : $i;

        return [
            'busca'             => mb_substr($this->query('busca'), 0, 100),
            'cliente_id'        => $inteiro($this->query('cliente_id')),
            'obrigacao_id'      => $inteiro($this->query('obrigacao_id')),
            'status'            => $opcao($this->query('status'), array_keys(Documento::STATUS)),
            'pendencia'         => $opcao($this->query('pendencia'), array_keys(Documento::PENDENCIAS)),
            'canal_recebimento' => $opcao($this->query('canal_recebimento'), array_keys(Documento::CANAIS)),
            'ordenar'           => $opcao($this->query('ordenar'), array_keys(Documento::ORDENACOES)) ?: 'prioridade',
        ];
    }

    private function exibirFicha(array $documento, array $erros = [], string $motivo = ''): void
    {
        $this->view('documentos/ver', [
            'titulo'            => $documento['titulo'],
            'documento'         => $documento,
            'arquivoDisponivel' => Upload::caminho($documento['arquivo_armazenado']) !== null,
            'erros'             => $erros,
            'motivo'            => $motivo,
        ]);
    }

    private function formulario(?array $documento, array $valores, array $erros = []): void
    {
        $restrito = Auth::clienteRestrito();

        $this->view('documentos/form', [
            'titulo'     => $documento === null ? 'Solicitar documento' : 'Editar documento',
            'documento'  => $documento,
            'valores'    => $valores,
            'erros'      => $erros,
            'clientes'   => $documento === null ? (new Cliente())->listarParaSelecao(false, null, $restrito) : [],
            'obrigacoes' => $documento === null ? (new Obrigacao())->listarParaSelecao($restrito) : [],
        ]);
    }

    private function formularioRecebimento(array $documento, array $valores, array $erros = []): void
    {
        $this->view('documentos/receber', [
            'titulo'    => 'Registrar recebimento',
            'documento' => $documento,
            'valores'   => $valores,
            'erros'     => $erros,
            'limite'    => Upload::tamanhoMaximo(),
        ]);
    }

    private function documentoOu404(string $id): array
    {
        $documento = $this->documentos->buscarPorId($this->id($id), Auth::clienteRestrito());

        if ($documento === null) {
            throw new HttpException(404, 'Documento não encontrado.');
        }

        return $documento;
    }
}
