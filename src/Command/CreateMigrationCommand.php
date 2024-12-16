<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMigrationCommand extends Command
{
    protected static $defaultName = 'clickhouse:migrate:create';

    private string $migrationTemplatePath;
    private string $migrationsDir;

    public function __construct(string $migrationTemplatePath, string $migrationsDir)
    {
        parent::__construct();
        $this->migrationTemplatePath = $migrationTemplatePath;
        $this->migrationsDir = $migrationsDir;
    }

    protected function configure()
    {
        $this
            ->setDescription('Создает новую миграцию')
            ->addArgument('name', InputArgument::REQUIRED, 'Название миграции');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Получаем последнюю версию
        $lastVersion = $this->getLastMigrationVersion();

        // Увеличиваем версию на 1
        $newVersion = $lastVersion + 1;

        $className = 'Version' . $newVersion . '_' . $this->normalizeClassName($name);
        $fileName = $this->migrationsDir . '/' . $className . '.php';

        if (!file_exists($this->migrationTemplatePath)) {
            $output->writeln('<error>Файл шаблона миграции не найден.</error>');
            return Command::FAILURE;
        }

        $template = file_get_contents($this->migrationTemplatePath);
        $template = str_replace('{{className}}', $className, $template);

        if (!is_dir($this->migrationsDir)) {
            mkdir($this->migrationsDir, 0777, true);
        }

        file_put_contents($fileName, $template);

        $output->writeln("<info>Миграция создана: $fileName</info>");
        $output->writeln('<comment>Не забудьте добавить код в методы up() и down().</comment>');

        return Command::SUCCESS;
    }

    private function getLastMigrationVersion(): int
    {
        $migrationFiles = glob($this->migrationsDir . '/Version*.php');
        $versions = [];

        foreach ($migrationFiles as $file) {
            $baseName = basename($file, '.php');
            if (preg_match('/Version(\d+)_/', $baseName, $matches)) {
                $versions[] = (int)$matches[1];
            }
        }

        return $versions ? max($versions) : 0;
    }

    private function normalizeClassName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    }
}