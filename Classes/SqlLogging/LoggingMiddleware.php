<?php

// can be used instead after TYPO3 support is set to >=12

declare(strict_types=1);

namespace Kanti\ServerTiming\SqlLogging;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

if (!interface_exists(MiddlewareInterface::class)) {
    return;
}

final class LoggingMiddleware implements MiddlewareInterface
{
    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new LoggingDriver($driver, new DoctrineSqlLogger());
    }
}
