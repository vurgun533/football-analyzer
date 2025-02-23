<?php

interface DatabaseInterface {
    public function connect(): void;
    public function disconnect(): void;
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): bool;
} 