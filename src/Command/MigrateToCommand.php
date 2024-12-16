<?php

namespace App\Command;

use App\MigrationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateToCommand extends Command
{
    protected static $defaultName = 'clickhouse:migrate:to';

    private MigrationManager $migrationManager;

    public function __construct(MigrationManager $migrationManager)
    {
        parent::__construct();
        $this->migrationManager = $migrationManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Мигрирует базу данных ClickHouse до указанной версии')
            ->addArgument('version', InputArgument::REQUIRED, 'Целевая версия миграции');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = (int)$input->getArgument('version');
        $this->migrationManager->setOutput($output);

        try {
            $this->migrationManager->migrateToVersion($version);
            $output->writeln("<info>Миграция до версии {$version} успешно выполнена.</info>");
        } catch (\Exception $e) {
            $output->writeln('<error>Ошибка: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}