<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Doctrine\DBAL\Logging\LoggerChain;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Adminpanel\Log\DoctrineSqlLogger;
use TYPO3\CMS\Adminpanel\Utility\StateUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdminpanelSqlLoggingMiddleware implements MiddlewareInterface
{
    /**
     * Enable SQL Logging as early as possible to catch all queries if the admin panel is active
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (StateUtility::isActivatedForUser() && StateUtility::isOpen()) {
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
            $connection->getConfiguration()->setSQLLogger(
                new LoggerChain(
                    array_filter(
                        [
                            GeneralUtility::makeInstance(DoctrineSqlLogger::class),
                            $connection->getConfiguration()->getSQLLogger(),
                        ]
                    )
                )
            );
        }
        return $handler->handle($request);
    }
}
