<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

use DateTime;
use PDO;

/**
 * Conexão PDO única por requisição (aberta somente quando usada).
 */
final class Database
{
    private static ?PDO $conexao = null;

    public static function conexao(): PDO
    {
        if (self::$conexao === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                config('db.host'),
                config('db.port'),
                config('db.nome')
            );

            self::$conexao = new PDO($dsn, config('db.usuario'), config('db.senha'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Prepared statements reais no servidor (não emulados).
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Mantém CURDATE()/NOW() do MySQL no mesmo fuso do PHP.
            // Essencial para o Radar (vencimentos calculados por data).
            $stmt = self::$conexao->prepare('SET time_zone = ?');
            $stmt->execute([(new DateTime())->format('P')]);
        }

        return self::$conexao;
    }
}
