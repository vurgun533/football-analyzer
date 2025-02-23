<?php

class MySQLDatabase implements DatabaseInterface
{
    private $connection = null;
    private $config;

    public function __construct(DatabaseConfig $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->config->getHost()};dbname={$this->config->getDbname()};charset=utf8mb4";
            $this->connection = new PDO(
                $dsn,
                $this->config->getUsername(),
                $this->config->getPassword(),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Veritabanına bağlanırken hata: " . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }

    public function lastInsertId(): int {
        return (int) $this->connection->lastInsertId();
    }
} 