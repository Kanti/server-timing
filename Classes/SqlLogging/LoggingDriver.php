<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class LoggingDriver extends AbstractDriverMiddleware
{
    public function __construct(DriverInterface $driver, private readonly DoctrineSqlLogger $logger)
    {
        parent::__construct($driver);
    }

    public function connect(array $params)
    {
        return new LoggingConnection(parent::connect($params), $this->logger);
    }
}
