<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Kanti\ServerTiming\SqlLogging\SqlLoggerCore11;
use Kanti\ServerTiming\Utility\TimingUtility;
use TYPO3\CMS\Core\Core\Event\BootCompletedEvent;

final class BootCompletedEventListener
{
    public function __invoke(BootCompletedEvent $event): void
    {
        SqlLoggerCore11::registerSqlLogger();
    }
}
