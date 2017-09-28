<?php

namespace Kutny\CronBundle;

use Kutny\DateTimeBundle\DateTime;
use Kutny\DateTimeBundle\DateTimeFactory;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CronRunnerCommand extends Command
{
    private $symfonyEnvironment;
    private $projectBaseDirectory;
    private $container;
    private $cronCommandManager;
    private $dateTimeFactory;

    public function __construct(
        $symfonyEnvironment,
        $projectBaseDirectory,
        ContainerInterface $container,
        CronCommandManager $cronCommandManager,
        DateTimeFactory $dateTimeFactory
    ) {
        $this->symfonyEnvironment = $symfonyEnvironment;
        $this->projectBaseDirectory = realpath($projectBaseDirectory);
        $this->container = $container;
        $this->cronCommandManager = $cronCommandManager;
        $this->dateTimeFactory = $dateTimeFactory;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cron:run');
        $this->setDescription('Runs all cron jobs scheduled for current time');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $successfulJobCount = $failedJobCount = 0;
        $cronStartedAt = $this->dateTimeFactory->now();
        $commandsToBeRun = $this->getCommandsToBeRun($cronStartedAt);
        $processes = $this->startProcesses($commandsToBeRun);

        while (count($processes) > 0) {
            foreach ($processes as $index => $process) {
                if (!$process->isRunning()) {
                    $commandName = $this->getCommandNameFromProcess($process);

                    if ($process->isSuccessful()) {
                        $successfulJobCount++;
                        $output->writeln('<info>' . $commandName . ' successfully finished</info>');

                        $this->getLogger()->info($commandName, [
                            'startedAt' => $cronStartedAt->toFormat('Y-m-d H:i:s'),
                            'finishedAt' => $this->dateTimeFactory->now()->toFormat('Y-m-d H:i:s'),
                            'elapsedTime' => $this->timer()
                        ]);
                    }
                    else {
                        $failedJobCount++;
                        $errorMessage = $this->getErrorMessage($process->getErrorOutput(), $commandName);

                        $output->writeln('<error>' . $commandName . ' failed</error>');
                        $output->writeln('- error: ' . $errorMessage);

                        $this->getLogger()->error($commandName, [
                            'startedAt' => $cronStartedAt->toFormat('Y-m-d H:i:s'),
                            'finishedAt' => $this->dateTimeFactory->now()->toFormat('Y-m-d H:i:s'),
                            'elapsedTime' => $this->timer(),
                            'errorMessage' => $errorMessage,
                        ]);
                    }

                    unset($processes[$index]);
                }
            }

            sleep(1);
        }

        $message = $successfulJobCount . ' cron jobs successfully completed, ' . $failedJobCount . ' failed (total jobs: ' . count($commandsToBeRun) . ')';

        if ($failedJobCount === 0) {
            $output->writeln('<info>' . $message . '</info>');
        }
        else {
            $output->writeln('<error>' . $message . '</error>');
        }
    }

    private function getCommandsToBeRun(DateTime $cronStartedAt)
    {
        $cronJobServiceIds = $this->cronCommandManager->getCronCommandServices();

        $commandsToBeRun = [];

        foreach ($cronJobServiceIds as $cronJobServiceId) {
            /** @var ICronCommand $command */
            $command = $this->container->get($cronJobServiceId);

            if (!$command instanceof ICronCommand) {
                $this->getLogger()->error('Cron command must implement the ' . ICronCommand::class . ' interface');
                continue;
            }

            if ($command->shouldBeRun($cronStartedAt->getDate(), $cronStartedAt->getTime())) {
                $commandsToBeRun[] = $command;
            }
        }

        return $commandsToBeRun;
    }

    private function startProcesses(array $commandsToBeRun)
    {
        $this->timer();

        /** @var Process[] $processes */
        $processes = [];

        foreach ($commandsToBeRun as $command) {
            $processes[] = $this->runCommandInProcess($command);
        }

        return $processes;
    }

    private function runCommandInProcess(Command $command)
    {
        $processCommand = $this->projectBaseDirectory . '/bin/console ' . $command->getName() . ' --env=' . $this->symfonyEnvironment;

        $process = new Process($processCommand);
        $process->start();

        return $process;
    }

    private function getCommandNameFromProcess(Process $process)
    {
        if (!preg_match('~bin/console ([^\s]+)~', $process->getCommandLine(), $matches)) {
            throw new \Exception('Unexpected command line: ' . $process->getCommandLine());
        }

        return $matches[1];
    }

    private function getErrorMessage($errorOutput, $commandName)
    {
        $errorOutput = str_replace('[Exception]', '', $errorOutput);
        $errorOutput = str_replace($commandName, '', $errorOutput);
        $errorOutput = trim($errorOutput);

        return $errorOutput;
    }

    private function timer()
    {
        static $time = [];
        $now = microtime(true);
        $delta = isset($time['cron']) ? $now - $time['cron'] : 0;
        $time['cron'] = $now;
        return $delta;
    }

    private function getLogger()
    {
        return $this->container->get('kutny_cron_bundle.logger');
    }
}
