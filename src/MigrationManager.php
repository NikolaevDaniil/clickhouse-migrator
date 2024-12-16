<?php

namespace App;

use ClickHouseDB\Client;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationManager
{
    private Client $client;
    private string $migrationsDir;
    private string $tableName;
    private ?OutputInterface $output = null;

    public function __construct(Client $client, string $migrationsDir, string $tableName = 'schema_migrations')
    {
        $this->client = $client;
        $this->migrationsDir = $migrationsDir;
        $this->tableName = $tableName;
        $this->ensureMigrationTable();
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
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
        $this->client->write($sql);
    }

    public function migrateUp(?int $targetVersion = null): void
    {
        $appliedVersions = $this->getAppliedMigrations();
        $allMigrations = $this->getAllMigrations();

        // Проверяем, что все применённые миграции существуют на диске
        foreach ($appliedVersions as $version) {
            if (!isset($allMigrations[$version])) {
                throw new \Exception("Миграция версии {$version} присутствует в базе данных, но отсутствует в файлах.");
            }
        }

        // Сортируем миграции по версии
        ksort($allMigrations);

        // Применяем миграции
        foreach ($allMigrations as $version => $migration) {
            if (in_array($version, $appliedVersions, true)) {
                continue;
            }

            if ($targetVersion !== null && $version > $targetVersion) {
                break;
            }

            $this->applyMigration($version, $migration);
        }
    }

    /**
     * @throws Exception
     */
    public function migrateDown(?int $targetVersion = null): void
    {
        $appliedVersions = $this->getAppliedMigrations();
        $allMigrations = $this->getAllMigrations();

        // Проверяем, что все применённые миграции существуют на диске
        foreach ($appliedVersions as $version) {
            if (!isset($allMigrations[$version])) {
                throw new \Exception("Миграция версии {$version} присутствует в базе данных, но отсутствует в файлах.");
            }
        }

        // Сортируем миграции в обратном порядке
        krsort($allMigrations);

        // Откатываем миграции
        foreach ($allMigrations as $version => $migration) {
            if (!in_array($version, $appliedVersions, true)) {
                continue;
            }

            if ($targetVersion !== null && $version <= $targetVersion) {
                break;
            }

            $this->revertMigration($version, $migration);
        }
    }

    /**
     * @throws \Exception
     */
    public function rollbackLastMigration(): void
    {
        $appliedVersions = $this->getAppliedMigrations();

        if (empty($appliedVersions)) {
            $this->output->writeln('<comment>Нет миграций для отката.</comment>');
            return;
        }

        // Получаем последнюю применённую версию
        $lastVersion = max($appliedVersions);
        $migration = $this->getMigrationByVersion($lastVersion);

        if ($migration === null) {
            throw new \Exception("Миграция версии {$lastVersion} присутствует в базе данных, но отсутствует в файлах.");
        }

        $this->revertMigration($lastVersion, $migration);
    }

    /**
     * @throws Exception
     */
    public function migrateToVersion(int $targetVersion): void
    {
        $appliedVersions = $this->getAppliedMigrations();
        $currentVersion = $appliedVersions ? max($appliedVersions) : 0;

        if ($currentVersion < $targetVersion) {
            $this->migrateUp($targetVersion);
        } elseif ($currentVersion > $targetVersion) {
            $this->migrateDown($targetVersion);
        } else {
            $this->output->writeln('<info>База данных уже на версии ' . $targetVersion . '</info>');
        }
    }

    public function getMigrationsStatus(): array
    {
        $appliedVersions = $this->getAppliedMigrations();
        $allMigrations = $this->getAllMigrations();

        $status = [];
        foreach ($allMigrations as $version => $migration) {
            $isApplied = in_array($version, $appliedVersions, true);
            $status[] = [
                'version' => $version,
                'name' => $migration['name'],
                'applied' => $isApplied,
            ];
        }

        // Проверяем миграции, которые есть в базе данных, но отсутствуют в файлах
        foreach ($appliedVersions as $version) {
            if (!isset($allMigrations[$version])) {
                $status[] = [
                    'version' => $version,
                    'name' => 'Отсутствует в файлах',
                    'applied' => true,
                ];
            }
        }

        // Сортируем по версии
        usort($status, function ($a, $b) {
            return $a['version'] <=> $b['version'];
        });

        return $status;
    }

    /**
     * @throws Exception
     */
    private function applyMigration(int $version, array $migration): void
    {
        $className = $migration['class'];

        if (!class_exists($className)) {
            require_once $migration['file'];
        }

        if (!class_exists($className)) {
            throw new \Exception("Класс миграции {$className} не найден.");
        }

        /** @var MigrationInterface $instance */
        $instance = new $className($this->client);

        try {
            $instance->up();
            $this->saveMigration($version, $migration['name']);
            $this->output->writeln("<info>Применена миграция: {$migration['name']} (версия {$version})</info>");
        } catch (Exception $e) {
            $this->output->writeln("<error>Ошибка при применении миграции версии {$version}: {$e->getMessage()}</error>");
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function revertMigration(int $version, array $migration): void
    {
        $className = $migration['class'];

        if (!class_exists($className)) {
            require_once $migration['file'];
        }

        if (!class_exists($className)) {
            throw new Exception("Класс миграции {$className} не найден.");
        }

        /** @var MigrationInterface $instance */
        $instance = new $className($this->client);

        try {
            $instance->down();
            $this->removeMigration($version);
            $this->output->writeln("<info>Откатена миграция: {$migration['name']} (версия {$version})</info>");
        } catch (Exception $e) {
            $this->output->writeln("<error>Ошибка при откате миграции версии {$version}: {$e->getMessage()}</error>");
            throw $e;
        }
    }

    private function getAllMigrations(): array
    {
        $migrations = [];
        foreach (glob($this->migrationsDir . '/Version*.php') as $file) {
            $baseName = basename($file, '.php');
            if (preg_match('/Version(\d+)_(.+)/', $baseName, $matches)) {
                $version = (int)$matches[1];
                $name = $matches[2];
                $className = 'Migrations\\' . $baseName;

                $migrations[$version] = [
                    'version' => $version,
                    'name' => $name,
                    'class' => $className,
                    'file' => $file,
                ];
            }
        }
        return $migrations;
    }

    private function getMigrationByVersion(int $version): ?array
    {
        $migrationFiles = glob($this->migrationsDir . "/Version{$version}_*.php");
        if (empty($migrationFiles)) {
            return null;
        }

        $file = $migrationFiles[0];
        $baseName = basename($file, '.php');
        if (preg_match('/Version(\d+)_(.+)/', $baseName, $matches)) {
            $name = $matches[2];
            $className = 'Migrations\\' . $baseName;

            return [
                'version' => $version,
                'name' => $name,
                'class' => $className,
                'file' => $file,
            ];
        }
        return null;
    }

    private function getAppliedMigrations(): array
    {
        $result = $this->client->select("SELECT version FROM {$this->tableName}")->rows();
        return array_column($result, 'version');
    }

    private function saveMigration(int $version, string $name): void
    {
        $sql = "INSERT INTO {$this->tableName} (version, name) VALUES ($version, '$name')";
        $this->client->write($sql);
    }

    private function removeMigration(int $version): void
    {
        $sql = "ALTER TABLE {$this->tableName} DELETE WHERE version = $version";
        $this->client->write($sql);
    }
}