<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service\RegisterShutdownFunction;

interface RegisterShutdownFunctionInterface
{
    public function register(callable $callback): void;
}
