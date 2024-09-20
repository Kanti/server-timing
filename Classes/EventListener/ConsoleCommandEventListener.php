<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Utility\TimingUtility;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Kanti\ServerTiming\Dto\StopWatch;

final class ConsoleCommandEventListener
{
    /** @var StopWatch[] */
    private array $stopWatches = [];

    public function start(ConsoleCommandEvent $event): void
    {
        $this->stopWatches[] = TimingUtility::stopWatch('console.command', (string)$event->getCommand()?->getName());
    }

    public function stop(ConsoleTerminateEvent $event): void
    {
        $stopWatch = array_pop($this->stopWatches);
        if ($stopWatch === null) {
            throw new \Exception('No stopWatch found, did you start the command already?');
        }
        $stopWatch->stop();
        if (!$this->stopWatches) {
            TimingUtility::getInstance()->shutdown(ScriptResult::fromCli($event->getExitCode()));
        }
    }
}
