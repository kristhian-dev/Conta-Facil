<?php

declare(strict_types=1);

namespace App\Models;

defined('APP_ROOT') || exit;

use App\Core\Model;

/**
 * Catálogo de tipos de obrigação (somente leitura nesta versão).
 */
final class TipoObrigacao extends Model
{
    /**
     * @return array<int, array{id: int, nome: string, periodicidade: string}>
     */
    public function listar(): array
    {
        return $this->executar('SELECT id, nome, descricao, periodicidade FROM tipos_obrigacao ORDER BY nome')->fetchAll();
    }
}
