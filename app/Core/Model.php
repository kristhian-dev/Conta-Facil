<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

use PDO;
use PDOStatement;

/**
 * Base dos Models: fornece a conexão PDO.
 * Todo SQL da aplicação fica nos Models, sempre com prepared statements.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conexao();
    }

    /**
     * Prepara e executa uma consulta com parâmetros posicionais (?),
     * informando o tipo de cada valor ao PDO. Inteiros são enviados como
     * PARAM_INT, o que é necessário para LIMIT/OFFSET.
     */
    protected function executar(string $sql, array $parametros = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);

        foreach (array_values($parametros) as $indice => $valor) {
            $tipo = match (true) {
                is_int($valor)  => PDO::PARAM_INT,
                is_bool($valor) => PDO::PARAM_BOOL,
                $valor === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($indice + 1, $valor, $tipo);
        }

        $stmt->execute();

        return $stmt;
    }
}
