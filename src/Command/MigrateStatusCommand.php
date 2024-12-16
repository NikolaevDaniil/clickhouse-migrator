<?php

namespace App\Command;

use App\MigrationManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateStatusCommand extends Command
{
    protected static $defaultName = 'clickhouse:migrate:status';

    private MigrationManager $migrationManager;

    public function __construct(MigrationManager $migrationManager)
    {
        parent::__construct();
        $this->migrationManager = $migrationManager;
    }

    protected function configure()
    {
        $this->setDescription('Отображает статус миграций');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $statusList = $this->migrationManager->getMigrationsStatus();

        $output->writeln('<info>Статус миграций:</info>');

        foreach ($statusList as $status) {
            $applied = $status['applied'] ? '<comment>Применена</comment>' : '<error>Не применена</error>';
            $output->writeln(" - Версия {$status['version']}: {$status['name']} - {$applied}");
        }

        return Command::SUCCESS;
    }
}