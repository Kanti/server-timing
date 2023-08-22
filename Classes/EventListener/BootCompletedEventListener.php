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
        // we initialize TimingUtility here
        // in the install tool, (eg. DB compare)
        // at this point, the TimingUtility is found in the container, but at the shutdown state the TimingUtility is not found in the conatiner.
        // so if we initialize it right here and save it inside a static variable, then everything works as expected. (not the sentry part :/ )
        TimingUtility::getInstance();
        SqlLoggerCore11::registerSqlLogger();
    }
}
