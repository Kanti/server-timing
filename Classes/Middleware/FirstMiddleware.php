<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Doctrine\DBAL\Logging\SQLLogger;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class FirstMiddleware implements MiddlewareInterface
{
    public static ?StopWatch $stopWatchOutward = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $inward = TimingUtility::stopWatch('middleware', 'Inward');
        $request = $request->withAttribute('server-timing:middleware:inward', $inward);

        $GLOBALS['TYPO3_REQUEST'] = $request;

        $this->registerSqlLogger();

        try {
            $response = $handler->handle($request);
        } catch (ImmediateResponseException $immediateResponseException) {
            $response = $immediateResponseException->getResponse();
        }

        $inward->stopIfNot();
        self::$stopWatchOutward?->stopIfNot();
        self::$stopWatchOutward = null;

        return TimingUtility::getInstance()->shutdown($request, $response);
    }

    /**
     * @deprecated can be removed if only TYPO3 >=12 is compatible
     */
    private function registerSqlLogger(): void
    {
        if (version_compare((new Typo3Version())->getBranch(), '12.0', '>=')) {
            return;
        }

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->getConfiguration()->setSQLLogger(
            new class implements SQLLogger {
                private ?StopWatch $stopWatch = null;

                public function startQuery($sql, ?array $params = null, ?array $types = null): void
                {
                    $stop = $this->stopWatch;
                    if ($stop instanceof StopWatch) {
                        $stop();
                    }

                    $this->stopWatch = TimingUtility::stopWatch('sql', $sql);
                }

                public function stopQuery(): void
                {
                    $stop = $this->stopWatch;
                    if ($stop instanceof StopWatch) {
                        $stop();
                        $this->stopWatch = null;
                    }
                }
            }
        );
    }
}
