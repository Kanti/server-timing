<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service\RegisterShutdownFunction;

final class RegisterShutdownFunctionNoop implements RegisterShutdownFunctionInterface
{
    public int $callCount = 0;

    public function register(callable $callback): void
    {
        $this->callCount++;
    }
}
