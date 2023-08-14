<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service\RegisterShutdownFunction;

final class RegisterShutdownFunction implements RegisterShutdownFunctionInterface
{
    public function register(callable $callback): void
    {
        register_shutdown_function($callback);
    }
}
