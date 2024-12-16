<?php

namespace App\Migrations;

use App\Migration;

class Version1_create_users_tabl8 extends Migration
{
    public function up(): void
    {
        $this->client->write("
            CREATE TABLE IF NOT EXISTS example_table (
                id UInt32,
                name String
            ) ENGINE = MergeTree()
            ORDER BY id
        ");
    }

    public function down(): void
    {
        $this->client->write("DROP TABLE IF EXISTS example_table");
    }
}
