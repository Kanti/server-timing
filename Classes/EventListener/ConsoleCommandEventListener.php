<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Exception;
use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

final class ConsoleCommandEventListener
{
    /** @var StopWatch[] */
    private array $stopWatches = [];

    #[AsEventListener('kanti/server-timing/console-command-event-listener')]
    public function start(ConsoleCommandEvent $event): void
    {
        $this->stopWatches[] = TimingUtility::stopWatch('console.command', (string)$event->getCommand()?->getName());
    }

    #[AsEventListener('kanti/server-timing/console-terminate-event-listener')]
    public function stop(ConsoleTerminateEvent $event): void
    {
        $stopWatch = array_pop($this->stopWatches);
        if ($stopWatch === null) {
            throw new Exception('No stopWatch found, did you start the command already?', 7800196394);
        }

        $stopWatch->stop();
        if (!$this->stopWatches) {
            TimingUtility::getInstance()->shutdown(ScriptResult::fromCli($event->getExitCode()));
        }

        $event->getOutput()->writeln(
            sprintf(
                '<info>server_timing:</info> Command "%s" took %.4fs',
                (string)$event->getCommand()?->getName(),
                $stopWatch->getDuration(),
            ),
            OutputInterface::VERBOSITY_VERBOSE,
        );
    }
}
