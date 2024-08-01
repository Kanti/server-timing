<?php

// can be used instead after TYPO3 support is set to >=12

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as DriverStatement;

if (!class_exists(AbstractConnectionMiddleware::class)) {
    return;
}

final class LoggingConnection extends AbstractConnectionMiddleware
{
    public function __construct(ConnectionInterface $connection, private readonly DoctrineSqlLogger $logger)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): DriverStatement
    {
        return new LoggingStatement(parent::prepare($sql), $this->logger, $sql);
    }

    public function query(string $sql): Result
    {
        $this->logger->startQuery($sql);
        $query = parent::query($sql);
        $this->logger->stopQuery();

        return $query;
    }

    public function exec(string $sql): int
    {
        $this->logger->startQuery($sql);
        $query = parent::exec($sql);
        $this->logger->stopQuery();

        return (int)$query;
    }
}
