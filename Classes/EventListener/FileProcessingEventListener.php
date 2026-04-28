<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;
use TYPO3\CMS\Core\Resource\Event\BeforeFileProcessingEvent;

final class FileProcessingEventListener
{
    public StopWatch|null $stopWatch = null;

    #[AsEventListener('kanti/server-timing/file-processing')]
    public function before(BeforeFileProcessingEvent $event): void
    {
        if (!$event->getProcessedFile()->isProcessed()) {
            $this->stopWatch?->stopIfNot();
            $this->stopWatch = TimingUtility::stopWatch('fileProcessing', $event->getProcessedFile()->getName());
        }
    }

    #[AsEventListener('kanti/server-timing/file-processing')]
    public function after(AfterFileProcessingEvent $event): void
    {
        $this->stopWatch?->stopIfNot();
        $this->stopWatch = null;
    }
}
