<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

use RuntimeException;

/**
 * Erro HTTP controlado (403, 404, 405...).
 * Lançada por Router/Controllers e tratada no index.php,
 * que exibe a página de erro com o código correspondente.
 */
final class HttpException extends RuntimeException
{
    private const MENSAGENS = [
        400 => 'Requisição inválida.',
        403 => 'Você não tem permissão para acessar esta página.',
        404 => 'Página não encontrada.',
        405 => 'Método não permitido.',
        413 => 'O envio excede o tamanho máximo permitido.',
    ];

    /**
     * Falha na validação CSRF. Usa o código 403 (padrão HTTP): o Apache com
     * mod_php converte códigos não padronizados, como 419, em erro 500.
     */
    public static function csrfInvalido(): self
    {
        return new self(403, 'Sua sessão expirou ou o formulário é inválido. Recarregue a página e tente novamente.');
    }

    public function __construct(int $status, ?string $mensagem = null)
    {
        parent::__construct($mensagem ?? self::MENSAGENS[$status] ?? 'Erro.', $status);
    }
}
