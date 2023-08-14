<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\EventListener;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Mail\Event\AfterMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;

final class MailEventListener
{
    public ?StopWatch $stopWatch = null;

    public function start(BeforeMailerSentMessageEvent $event): void
    {
        $info = '';
        $message = $event->getMessage();
        if ($message instanceof Email) {
            $emails = implode(', ', array_map(static fn($address): string => $address->getAddress(), $message->getTo()));
            $info = $message->getSubject() . ' -> ' . $emails;
        }

        $this->stopWatch?->stopIfNot();
        $this->stopWatch = TimingUtility::stopWatch('mail', $info);
    }

    public function stop(AfterMailerSentMessageEvent $event): void
    {
        $this->stopWatch?->stopIfNot();
        $this->stopWatch = null;
    }
}
