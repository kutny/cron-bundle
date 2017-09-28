<?php

namespace Kutny\CronBundle;

use Kutny\DateTimeBundle\DateTime;
use Kutny\DateTimeBundle\DateTimeFactory;
use Kutny\DateTimeBundle\Time\Time;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CronDebuggerCommand extends Command
{
    private $container;
    private $cronCommandManager;
    private $dateTimeFactory;

    public function __construct(
        ContainerInterface $container,
        CronCommandManager $cronCommandManager,
        DateTimeFactory $dateTimeFactory
    ) {
        $this->container = $container;
        $this->cronCommandManager = $cronCommandManager;
        $this->dateTimeFactory = $dateTimeFactory;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cron:debug');
        $this->setDescription('Print out crons that are invoked on specified date/in specified period of time');
        $this->addArgument('date', InputArgument::REQUIRED);
        $this->addArgument('startTime', InputArgument::OPTIONAL);
        $this->addArgument('endTime', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $runs = $this->collectRuns($input, $output);

        $this->printRuns($runs, $output);
    }

    private function collectRuns(InputInterface $input, OutputInterface $output)
    {
        $cronJobServiceIds = $this->cronCommandManager->getCronCommandServices();
        $runs = [];

        foreach ($cronJobServiceIds as $cronJobServiceId) {
            /** @var ICronCommand $cronCommand */
            $cronCommand = $this->container->get($cronJobServiceId);

            $output->writeln('Checking ' . $cronCommand->getName());

            $currentDateTime = $this->getStartDateTime($input);
            $endDateTime = $this->getEndTimestamp($input);

            do {
                if ($cronCommand->shouldBeRun($currentDateTime->getDate(), $currentDateTime->getTime())) {
                    $runs[$currentDateTime->toTimestamp()][] = $cronCommand->getName();
                }

                $currentDateTime = $currentDateTime->addMinutes(5);
            }
            while ($currentDateTime->toTimestamp() <= $endDateTime->toTimestamp());
        }

        ksort($runs);

        return $runs;
    }

    private function getStartDateTime(InputInterface $input)
    {
        if ($input->getArgument('startTime')) {
            return $this->dateTimeFactory->fromFormat('Y-m-d H:i:s', $input->getArgument('date') . ' ' . $input->getArgument('startTime'));
        }
        else {
            $date = $this->dateTimeFactory->fromFormatDate($input->getArgument('date'));
            return new DateTime($date, new Time(0, 0, 0));
        }
    }

    private function getEndTimestamp(InputInterface $input)
    {
        if ($input->getArgument('endTime')) {
            return $this->dateTimeFactory->fromFormat('Y-m-d H:i:s', $input->getArgument('date') . ' ' . $input->getArgument('endTime'));
        }
        else {
            $date = $this->dateTimeFactory->fromFormatDate($input->getArgument('date'));
            return new DateTime($date->addDays(1), new Time(0, 0, 0));
        }
    }

    private function printRuns(array $runs, OutputInterface $output)
    {
        foreach ($runs as $timestamp => $commandNames) {
            $output->writeln('<info>' . date('Y-m-d H:i:s', $timestamp) . '</info>');

            foreach ($commandNames as $commandName) {
                $output->writeln(' * ' . $commandName);
            }
        }
    }
}
