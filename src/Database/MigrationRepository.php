<?php
namespace App\Database;

class MigrationRepository
{
    private DatabaseConnection $db;
    private string $tableName = 'schema_migrations';

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
        $this->ensureMigrationTable();
    }

    private function ensureMigrationTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                version UInt32,
                name String,
                apply_time DateTime DEFAULT now()
            ) ENGINE = MergeTree()
            ORDER BY version
        ";
        $this->db->execute($sql);
    }

    public function getAppliedMigrations(): array
    {
        $result = $this->db->query("SELECT version FROM {$this->tableName}");
        return array_column($result, 'version');
    }

    public function saveMigration(int $version, string $name): void
    {
        $sql = "INSERT INTO {$this->tableName} (version, name) VALUES ($version, '$name')";
        $this->db->execute($sql);
    }

    public function removeMigration(int $version): void
    {
        $sql = "ALTER TABLE {$this->tableName} DELETE WHERE version = $version";
        $this->db->execute($sql);
    }
}