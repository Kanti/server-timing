<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Doctrine\DBAL\Logging\SQLLogger;
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
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        TimingUtility::start('middleware', 'Inward');
        $this->registerSqlLogger();
        $response = $handler->handle($request);
        TimingUtility::end('middleware');
        return $response;
    }

    protected function registerSqlLogger(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->getConfiguration()->setSQLLogger(
            new class implements SQLLogger {
                public function startQuery($sql, ?array $params = null, ?array $types = null)
                {
                    TimingUtility::start('sql', $sql);
                }

                public function stopQuery()
                {
                    TimingUtility::end('sql');
                }
            }
        );
    }
}
