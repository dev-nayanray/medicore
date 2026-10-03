<?php

declare(strict_types=1);

namespace Seeders;

use PDO;

/**
 * Base seeder contract. Each seeder is idempotent: it truncates the
 * tables it owns (children first) and re-inserts, so `php console seed`
 * can safely run repeatedly.
 */
abstract class Seeder
{
    abstract public static function run(PDO $db): void;

    /** Human label used in console output. */
    public static function label(): string
    {
        return static::class;
    }

    /** Order in which the seeder runner executes. */
    public static function order(): int
    {
        return 100;
    }

    /** Truncate tables (children before parents). */
    protected static function truncate(PDO $db, string ...$tables): void
    {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $db->exec("TRUNCATE TABLE `{$table}`");
        }
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
