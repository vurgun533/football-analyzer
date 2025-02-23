<?php

class DatabaseConfig
{
    private $host;
    private $dbname;
    private $username;
    private $password;

    public function __construct()
    {
        $this->host = 'localhost';
        $this->dbname = 'football_analyzer';
        $this->username = 'root';
        $this->password = '';
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getDbname()
    {
        return $this->dbname;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }
} 