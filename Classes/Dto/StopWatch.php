<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Dto;

final class StopWatch
{
    public float $startTime;

    public ?float $stopTime = null;

    public function __construct(public string $key, public string $info)
    {
        $this->startTime = microtime(true);
    }

    public function getDuration(): float
    {
        $this->stopTime ??= microtime(true);
        return $this->stopTime - $this->startTime;
    }

    public function stop(): void
    {
        $this->stopTime = microtime(true);
    }

    public function __invoke(): void
    {
        $this->stop();
    }

    public function stopIfNot(): void
    {
        $this->stopTime ??= microtime(true);
    }
}
