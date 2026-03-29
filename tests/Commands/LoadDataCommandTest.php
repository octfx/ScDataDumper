<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Tests\Commands;

use Octfx\ScDataDumper\Commands\LoadData;
use Octfx\ScDataDumper\Tests\Fixtures\ScDataTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadDataCommandTest extends ScDataTestCase
{
    public function test_execute_runs_subcommands_in_order_and_forwards_flags(): void
    {
        $log = new CommandCallLog;

        $application = $this->makeApplication($log, [
            'generate:cache' => Command::SUCCESS,
            'load:items' => Command::SUCCESS,
            'load:blueprints' => Command::SUCCESS,
            'load:resource-types' => Command::SUCCESS,
            'load:vehicles' => Command::SUCCESS,
            'load:factions' => Command::SUCCESS,
            'load:manufacturers' => Command::SUCCESS,
            'load:translations' => Command::SUCCESS,
            'load:tags' => Command::SUCCESS,
        ]);

        $tester = new CommandTester($application->find('load:data'));
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
            '--overwrite' => true,
            '--scUnpackedFormat' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertSame(
            ['generate:cache', 'load:items', 'load:blueprints', 'load:resource-types', 'load:vehicles', 'load:factions', 'load:manufacturers', 'load:translations', 'load:tags'],
            array_column($log->calls, 'name')
        );
        self::assertTrue($log->calls[1]['options']['overwrite']);
        self::assertTrue($log->calls[1]['options']['scUnpackedFormat']);
        self::assertTrue($log->calls[2]['options']['overwrite']);
        self::assertTrue($log->calls[2]['options']['scUnpackedFormat']);
        self::assertTrue($log->calls[3]['options']['overwrite']);
        self::assertTrue($log->calls[4]['options']['overwrite']);
        self::assertTrue($log->calls[4]['options']['scUnpackedFormat']);
        self::assertTrue($log->calls[5]['options']['overwrite']);
        self::assertTrue($log->calls[5]['options']['scUnpackedFormat']);
    }

    public function test_execute_stops_on_first_failing_subcommand(): void
    {
        $log = new CommandCallLog;

        $application = $this->makeApplication($log, [
            'generate:cache' => Command::SUCCESS,
            'load:items' => Command::SUCCESS,
            'load:blueprints' => Command::SUCCESS,
            'load:resource-types' => Command::SUCCESS,
            'load:vehicles' => Command::FAILURE,
            'load:factions' => Command::SUCCESS,
            'load:manufacturers' => Command::SUCCESS,
            'load:translations' => Command::SUCCESS,
            'load:tags' => Command::SUCCESS,
        ]);

        $tester = new CommandTester($application->find('load:data'));
        $exitCode = $tester->execute([
            'scDataPath' => $this->tempDir,
            'jsonOutPath' => $this->tempDir,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame(['generate:cache', 'load:items', 'load:blueprints', 'load:resource-types', 'load:vehicles'], array_column($log->calls, 'name'));
    }

    /**
     * @param  array<string, int>  $results
     */
    private function makeApplication(CommandCallLog $log, array $results): Application
    {
        $application = new Application;
        $application->addCommand(new LoadData);
        $application->addCommand(new RecordingCommand('generate:cache', $log, $results['generate:cache'], ['path'], ['overwrite']));
        $application->addCommand(new RecordingCommand('load:items', $log, $results['load:items'], ['scDataPath', 'jsonOutPath'], ['overwrite', 'scUnpackedFormat']));
        $application->addCommand(new RecordingCommand('load:blueprints', $log, $results['load:blueprints'], ['scDataPath', 'jsonOutPath'], ['overwrite', 'scUnpackedFormat']));
        $application->addCommand(new RecordingCommand('load:resource-types', $log, $results['load:resource-types'], ['scDataPath', 'jsonOutPath'], ['overwrite']));
        $application->addCommand(new RecordingCommand('load:vehicles', $log, $results['load:vehicles'], ['scDataPath', 'jsonOutPath'], ['overwrite', 'scUnpackedFormat']));
        $application->addCommand(new RecordingCommand('load:factions', $log, $results['load:factions'], ['scDataPath', 'jsonOutPath'], ['overwrite', 'scUnpackedFormat']));
        $application->addCommand(new RecordingCommand('load:manufacturers', $log, $results['load:manufacturers'], ['scDataPath', 'jsonOutPath'], []));
        $application->addCommand(new RecordingCommand('load:translations', $log, $results['load:translations'], ['scDataPath', 'jsonOutPath'], []));
        $application->addCommand(new RecordingCommand('load:tags', $log, $results['load:tags'], ['scDataPath', 'jsonOutPath'], []));

        return $application;
    }
}

final class CommandCallLog
{
    /**
     * @var array<int, array{name: string, arguments: array<string, mixed>, options: array<string, bool>}>
     */
    public array $calls = [];
}

final class RecordingCommand extends Command
{
    /**
     * @param  array<int, string>  $arguments
     * @param  array<int, string>  $options
     */
    public function __construct(
        string $name,
        private readonly CommandCallLog $log,
        private readonly int $result,
        private readonly array $arguments,
        private readonly array $options,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        foreach ($this->arguments as $argument) {
            $this->addArgument($argument, InputArgument::OPTIONAL);
        }

        foreach ($this->options as $option) {
            $this->addOption($option, null, InputOption::VALUE_NONE);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argumentValues = [];
        foreach ($this->arguments as $argument) {
            $argumentValues[$argument] = $input->getArgument($argument);
        }

        $optionValues = [];
        foreach ($this->options as $option) {
            $optionValues[$option] = (bool) $input->getOption($option);
        }

        $this->log->calls[] = [
            'name' => $this->getName() ?? '',
            'arguments' => $argumentValues,
            'options' => $optionValues,
        ];

        return $this->result;
    }
}
