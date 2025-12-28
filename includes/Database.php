<?php
/**
 * AktMail - Veritabanı Bağlantı Sınıfı
 * 
 * Singleton pattern ile PDO bağlantısı yönetimi
 */

namespace AktMail;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $config;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }

    /**
     * Singleton instance döndürür
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Veritabanına bağlanır
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
        } catch (PDOException $e) {
            throw new PDOException("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }

    /**
     * PDO bağlantısını döndürür
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prepared statement çalıştırır
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Tek satır döndürür
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Tüm satırları döndürür
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert işlemi yapar ve lastInsertId döndürür
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Update işlemi yapar
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";

        $params = array_merge(array_values($data), $whereParams);
        $stmt = $this->query($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Delete işlemi yapar
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Transaction başlatır
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Transaction commit eder
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Transaction rollback eder
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Clone işlemini engeller (Singleton)
     */
    private function __clone()
    {
    }

    /**
     * Unserialize işlemini engeller (Singleton)
     */
    public function __wakeup()
    {
        throw new \Exception("Singleton sınıfı unserialize edilemez");
    }
}
