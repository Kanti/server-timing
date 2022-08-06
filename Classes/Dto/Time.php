<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Dto;

final class Time
{
    /** @var string */
    public $key;
    /** @var float[] */
    public $startTime = [];
    /** @var float[] */
    public $stopTime = [];
}
