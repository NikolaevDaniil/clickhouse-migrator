<?php

namespace App;

use ClickHouseDB\Client;

interface MigrationInterface
{
    public function __construct(Client $client);

    public function up(): void;

    public function down(): void;
}
