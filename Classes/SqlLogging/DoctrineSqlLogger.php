<?php

// can be used instead after TYPO3 support is set to >=12

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;

final class DoctrineSqlLogger
{
    private ?StopWatch $stopWatch = null;

    public function startQuery(string $sql): void
    {
        if ($sql === 'SELECT DATABASE()') {
            return;
        }

        $this->stopWatch?->stopIfNot();
        $this->stopWatch = TimingUtility::stopWatch('db', $sql);
    }

    public function stopQuery(): void
    {
        $this->stopWatch?->stopIfNot();
        $this->stopWatch = null;
    }
}
