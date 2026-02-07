<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private ?PDO $pdo = null;
    public string $last_error = '';

    public function __construct(string $dbPath)
    {
        try {
            $this->pdo = new PDO("sqlite:$dbPath");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
        }
    }

    public function query(string $query, array $params = []): PDOStatement|bool
    {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    public function get_results(string $query, array $params = []): array
    {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function get_row(string $query, array $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetch() : null;
    }

    public function insert(string $table, array $data, bool $replace = false): int|string|bool
    {
        $keys = array_keys($data);
        $fields = '"' . implode('", "', $keys) . '"';
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $op = $replace ? 'INSERT OR REPLACE' : 'INSERT';
        $sql = "$op INTO \"$table\" ($fields) VALUES ($placeholders)";
        $stmt = $this->query($sql, array_values($data));

        return $stmt ? (int)$this->pdo->lastInsertId() : false;
    }

    public function update(string $table, array $data, array $where): bool
    {
        $set = [];
        $params = [];
        foreach ($data as $key => $value) {
            $set[] = "\"$key\" = ?";
            $params[] = $value;
        }

        $where_clause = [];
        foreach ($where as $key => $value) {
            $where_clause[] = "\"$key\" = ?";
            $params[] = $value;
        }

        $sql = "UPDATE \"$table\" SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where_clause);
        return $this->query($sql, $params) !== false;
    }

    public function delete(string $table, array $where): bool
    {
        $where_clause = [];
        $params = [];
        foreach ($where as $key => $value) {
            $where_clause[] = "\"$key\" = ?";
            $params[] = $value;
        }

        $sql = "DELETE FROM \"$table\" WHERE " . implode(' AND ', $where_clause);
        return $this->query($sql, $params) !== false;
    }
}
