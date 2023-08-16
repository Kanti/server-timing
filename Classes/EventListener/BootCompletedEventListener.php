<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Kanti\ServerTiming\SqlLogging\SqlLoggerCore11;
use TYPO3\CMS\Core\Core\Event\BootCompletedEvent;

/**
 * @deprecated can be removed if only TYPO3 >=12 is compatible
 */
final class BootCompletedEventListener
{
    public function __invoke(BootCompletedEvent $event): void
    {
        SqlLoggerCore11::registerSqlLogger();
    }
}
