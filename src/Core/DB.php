<?php
declare(strict_types=1);

namespace GI\Core;

use PDO;
use PDOException;

class DB
{
    private static ?DB $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $_ENV['DB_HOST'] ?? '127.0.0.1',
            $_ENV['DB_PORT'] ?? '5432',
            $_ENV['DB_DATABASE'] ?? 'gi_portal'
        );

        $this->pdo = new PDO(
            $dsn,
            $_ENV['DB_USERNAME'] ?? 'gi_user',
            $_ENV['DB_PASSWORD'] ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): string
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $sql          = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) RETURNING id";
        $result       = $this->fetch($sql, $data);
        return $result ? (string) $result['id'] : '';
    }

    public function update(string $table, array $data, array $where): int
    {
        $set        = implode(', ', array_map(fn($k) => "{$k} = :set_{$k}", array_keys($data)));
        $conditions = implode(' AND ', array_map(fn($k) => "{$k} = :where_{$k}", array_keys($where)));
        $sql        = "UPDATE {$table} SET {$set} WHERE {$conditions}";
        $params     = [];
        foreach ($data as $k => $v) {
            $params["set_{$k}"] = $v;
        }
        foreach ($where as $k => $v) {
            $params["where_{$k}"] = $v;
        }
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $conditions = implode(' AND ', array_map(fn($k) => "{$k} = :{$k}", array_keys($where)));
        $sql        = "DELETE FROM {$table} WHERE {$conditions}";
        return $this->query($sql, $where)->rowCount();
    }
}
