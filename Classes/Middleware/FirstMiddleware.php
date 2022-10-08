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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class FirstMiddleware implements MiddlewareInterface
{
    /** @var StopWatch|null  */
    public static $stopWatchOutward = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $stop = TimingUtility::stopWatch('middleware', 'Inward');
        $request = $request->withAttribute('server-timing:middleware:inward', $stop);
        $this->registerSqlLogger();
        $response = $handler->handle($request);
        $stop = self::$stopWatchOutward;
        if ($stop instanceof StopWatch) {
            $stop();
        }
        return $response;
    }

    protected function registerSqlLogger(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->getConfiguration()->setSQLLogger(
            new class implements SQLLogger {
                /** @var StopWatch|null */
                private $stopWatch = null;

                public function startQuery($sql, ?array $params = null, ?array $types = null)
                {
                    $stop = $this->stopWatch;
                    if ($stop instanceof StopWatch) {
                        $stop();
                    }
                    $this->stopWatch = TimingUtility::stopWatch('sql', $sql);
                }

                public function stopQuery()
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
