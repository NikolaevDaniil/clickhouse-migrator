<?php

namespace App\Database;

use ClickHouseDB\Client;

class DatabaseConnection
{
    private Client $client;

    public function __construct(array $config)
    {
        $this->client = new Client([
            'host' => $config['host'],
            'port' => $config['port'],
            'username' => $config['username'],
            'password' => $config['password'],
        ]);

        $this->client->database($config['database']);
    }

    public function execute(string $sql): void
    {
        $this->client->write($sql);
    }

    public function query(string $sql): array
    {
        return $this->client->select($sql)->rows();
    }
}