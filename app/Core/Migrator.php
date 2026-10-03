<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

/**
 * SQL migration runner.
 *
 * Migrations live in database/migrations as NNN_description.php files that
 * return:  ['up' => fn(PDO), 'down' => fn(PDO), 'description' => '...']
 *
 * Applied migrations are tracked in the `migrations` table with a batch
 * number so rollbacks revert one batch at a time — the same semantics
 * Laravel popularized, with zero dependencies.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $path,
    ) {
        $this->ensureTracker();
    }

    public static function make(): self
    {
        return new self(Database::pdo(), BASE_PATH . '/database/migrations');
    }

    private function ensureTracker(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT UNSIGNED NOT NULL,
                `ran_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, array{file: string, applied: bool, batch: ?int, ran_at: ?string}> */
    public function status(): array
    {
        $applied = [];
        foreach (Database::query('SELECT migration, batch, ran_at FROM migrations') as $row) {
            $applied[$row['migration']] = $row;
        }

        $out = [];
        foreach ($this->files() as $file) {
            $name = basename($file, '.php');
            $row = $applied[$name] ?? null;
            $out[$name] = [
                'file'    => $file,
                'applied' => $row !== null,
                'batch'   => $row !== null ? (int) $row['batch'] : null,
                'ran_at'  => $row['ran_at'] ?? null,
            ];
        }
        return $out;
    }

    /** @return string[] absolute paths, sorted */
    private function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    /** @return string[] names of applied migrations */
    public function pending(): array
    {
        $pending = [];
        foreach ($this->status() as $name => $info) {
            if (!$info['applied']) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    /**
     * @return array{ran: string[], batch: int}
     */
    public function migrate(): array
    {
        $pending = $this->pending();
        if ($pending === []) {
            return ['ran' => [], 'batch' => 0];
        }

        $batch = (int) (Database::scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') ?? 0) + 1;

        // NOTE: no transaction wrapper — MySQL/MariaDB auto-commits DDL
        // (CREATE/DROP TABLE), so a wrapping transaction would break the
        // commit() call. Each migration runs sequentially instead.
        foreach ($pending as $name) {
            $migration = $this->load($name);

            ($migration['up'])($this->pdo);
            Database::execute('INSERT INTO migrations (migration, batch) VALUES (?, ?)', [$name, $batch]);

            Logger::info("Migrated: {$name}");
        }

        return ['ran' => $pending, 'batch' => $batch];
    }

    /** @return string[] rolled-back migration names */
    public function rollback(): array
    {
        $lastBatch = (int) (Database::scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') ?? 0);
        if ($lastBatch === 0) {
            return [];
        }

        $rows = Database::query('SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC', [$lastBatch]);
        $rolled = [];

        foreach ($rows as $row) {
            $name = (string) $row['migration'];
            $migration = $this->load($name);

            ($migration['down'])($this->pdo);
            Database::execute('DELETE FROM migrations WHERE migration = ?', [$name]);

            $rolled[] = $name;
            Logger::info("Rolled back: {$name}");
        }

        return $rolled;
    }

    /** Drop every table in the database (fresh start). */
    public function dropAllTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = Database::query('SHOW TABLES');
        foreach ($tables as $row) {
            $table = array_values($row)[0];
            if ($table === 'migrations') {
                continue;
            }
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->pdo->exec('DROP TABLE IF EXISTS `migrations`');
        $this->ensureTracker();
    }

    /** @return array{up: callable(PDO):void, down: callable(PDO):void} */
    private function load(string $name): array
    {
        $file = $this->path . '/' . $name . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Migration file missing: {$file}");
        }

        $migration = require $file;
        if (!is_array($migration) || !is_callable($migration['up'] ?? null) || !is_callable($migration['down'] ?? null)) {
            throw new RuntimeException("Migration [{$name}] must return ['up' => fn, 'down' => fn].");
        }

        return $migration;
    }
}
