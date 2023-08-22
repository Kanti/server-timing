<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\SqlLogging\SqlLoggerCore11;
use Kanti\ServerTiming\Utility\TimingUtility;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

final class ConsoleCommandEventListener
{
    public function start(ConsoleCommandEvent $event): void
    {
        TimingUtility::start('console.command', (string)$event->getCommand()?->getName());
    }

    public function stop(ConsoleTerminateEvent $event): void
    {
        TimingUtility::end('console.command');
        TimingUtility::getInstance()->shutdown(ScriptResult::fromCli($event->getExitCode()));
    }
}
