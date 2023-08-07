<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class LoggingMiddleWare implements MiddlewareInterface
{
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new LoggingDriver($driver, new DoctrineSqlLogger());
    }
}
