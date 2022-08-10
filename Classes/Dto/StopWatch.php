<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Dto;

final class StopWatch
{
    /** @var string */
    public $key = '';
    /** @var string */
    public $info = '';
    /** @var ?float */
    public $startTime;
    /** @var ?float */
    public $stopTime;

    public function __construct(string $key, string $info)
    {
        $this->key = $key;
        $this->info = $info;
        $this->startTime = microtime(true);
    }

    public function getDuration(): float
    {
        $this->stopTime = $this->stopTime ?? microtime(true);
        return $this->stopTime - $this->startTime;
    }

    public function __invoke(): void
    {
        $this->stopTime = microtime(true);
    }
}
