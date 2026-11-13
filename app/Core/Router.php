<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

/**
 * Roteador por query string (index.php?r=modulo/acao).
 *
 * Só executa rotas declaradas em config/routes.php. Para cada requisição:
 *   1. valida o formato e a existência da rota (404);
 *   2. confere o método HTTP (405);
 *   3. confere autenticação e perfil (redireciona ao login ou 403);
 *   4. valida o token CSRF em requisições POST (403);
 *   5. executa o método do controller.
 */
final class Router
{
    private const FORMATO_ROTA = '#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#';

    /**
     * @param array<string, array{metodos: string[], acao: array{0: class-string, 1: string}, acesso: string|string[]}> $rotas
     */
    public function __construct(private readonly array $rotas)
    {
    }

    public function despachar(string $rota, string $metodo): void
    {
        $rota   = trim($rota, '/');
        $metodo = strtoupper($metodo);

        if (!preg_match(self::FORMATO_ROTA, $rota) || !isset($this->rotas[$rota])) {
            throw new HttpException(404);
        }

        $definicao = $this->rotas[$rota];

        if (!in_array($metodo, $definicao['metodos'], true)) {
            header('Allow: ' . implode(', ', $definicao['metodos']));
            throw new HttpException(405);
        }

        $this->autorizar($definicao['acesso']);

        // Envio maior que post_max_size: o PHP descarta todo o corpo (inclusive
        // o token CSRF). Sem esta verificação o usuário veria "formulário inválido".
        if ($metodo === 'POST' && $_POST === [] && $_FILES === []
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > Upload::iniParaBytes((string) ini_get('post_max_size'))
        ) {
            throw new HttpException(413, 'O envio excede o tamanho máximo permitido de '
                . formatar_tamanho(Upload::tamanhoMaximo()) . '. Volte e escolha um arquivo menor.');
        }

        if ($metodo === 'POST' && !Csrf::validarRequisicao()) {
            throw HttpException::csrfInvalido();
        }

        [$classe, $acao] = $definicao['acao'];
        (new $classe())->$acao();
    }

    /**
     * @param string|string[] $acesso 'publico', 'autenticado' ou lista de perfis
     */
    private function autorizar(string|array $acesso): void
    {
        if ($acesso === 'publico') {
            return;
        }

        if (!Auth::verificar()) {
            redirecionar(config('auth.rota_login'));
        }

        if (is_array($acesso) && !Auth::temPerfil($acesso)) {
            throw new HttpException(403);
        }
    }
}
