<?php

namespace App\Command;

use App\MigrationManager;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MigrateUpCommand extends Command
{
    protected static $defaultName = 'clickhouse:migrate:up';

    private MigrationManager $migrationManager;

    public function __construct(MigrationManager $migrationManager)
    {
        parent::__construct();
        $this->migrationManager = $migrationManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Применяет миграции до указанной версии')
            ->addArgument('version', InputArgument::OPTIONAL, 'Версия, до которой нужно мигрировать')
            ->addOption('quiet', 'q', InputOption::VALUE_NONE, 'Тихий режим без подтверждений');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getArgument('version');
        $targetVersion = $version !== null ? (int)$version : null;

        $quiet = $input->getOption('quiet');

        if (!$quiet) {
            $helper = $this->getHelper('question');
            $questionText = 'Вы уверены, что хотите применить миграции? (yes/no) ';
            $question = new ConfirmationQuestion($questionText, false);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Операция отменена.</comment>');
                return Command::SUCCESS;
            }
        }

        $this->migrationManager->setOutput($output);

        try {
            $this->migrationManager->migrateUp($targetVersion);
        } catch (Exception $e) {
            $output->writeln('<error>Ошибка: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}