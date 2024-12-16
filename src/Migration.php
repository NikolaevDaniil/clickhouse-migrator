<?php

namespace App;

use ClickHouseDB\Client;

abstract class Migration implements MigrationInterface
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
