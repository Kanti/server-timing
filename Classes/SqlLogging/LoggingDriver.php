<?php

// can be used instead after TYPO3 support is set to >=12

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

if (!class_exists(AbstractDriverMiddleware::class)) {
    return;
}

final class LoggingDriver extends AbstractDriverMiddleware
{
    public function __construct(DriverInterface $driver, private readonly DoctrineSqlLogger $logger)
    {
        parent::__construct($driver);
    }

    public function connect(array $params): DriverInterface\Connection
    {
        return new LoggingConnection(parent::connect($params), $this->logger);
    }
}
