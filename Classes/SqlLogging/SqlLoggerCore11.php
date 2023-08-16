<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Doctrine\DBAL\Logging\SQLLogger;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @deprecated can be removed if only TYPO3 >=12 is compatible
 */
final class SqlLoggerCore11
{
    /**
     * @deprecated can be removed if only TYPO3 >=12 is compatible
     */
    public static function registerSqlLogger(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->getConfiguration()->setSQLLogger(
            new class implements SQLLogger {
                private ?StopWatch $stopWatch = null;

                public function startQuery($sql, ?array $params = null, ?array $types = null): void
                {
                    $this->stopWatch?->stopIfNot();

                    if ($sql === 'SELECT DATABASE()') {
                        return;
                    }

                    $this->stopWatch = TimingUtility::stopWatch('sql', $sql);
                }

                public function stopQuery(): void
                {
                    $this->stopWatch?->stopIfNot();
                    $this->stopWatch = null;
                }
            }
        );
    }
}
