<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service;

use Kanti\ServerTiming\Utility\TimingUtility;

final class ConfigService
{
    /** @var int */
    private const DEFAULT_STOP_WATCH_LIMIT = 100_000;
    private const DEFAULT_DESCRIPTION_LENGTH = 100;
    private const DEFAULT_NUMBER_TIMINGS = 70;

    public function stopWatchLimit(): int
    {
        return (int)($this->getConfig('stop_watch_limit') ?: self::DEFAULT_STOP_WATCH_LIMIT);
    }

    public function tracesSampleRate(): ?float
    {
        $tracesSampleRate = $this->getConfig(TimingUtility::IS_CLI ? 'sentry_cli_sample_rate' : 'sentry_sample_rate');
        return $tracesSampleRate === '' ? null : (float)$tracesSampleRate;
    }

    public function enableTracing(): ?bool
    {
        $tracesSampleRate = $this->tracesSampleRate();
        return $tracesSampleRate === null ? null : (bool)$tracesSampleRate;
    }

    public function getDescriptionLength(): int
    {
        return (int)($this->getConfig('length_of_description') ?: self::DEFAULT_DESCRIPTION_LENGTH);
    }

    public function getMaxNumberOfTimings(): int
    {
        return (int)($this->getConfig('number_of_timings') ?: self::DEFAULT_NUMBER_TIMINGS);
    }

    public function isMaxNumberOfTimingsSet(): bool
    {
        return (bool)($this->getConfig('number_of_timings'));
    }

    private function getConfig(string $path): string
    {
        return (string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing'][$path] ?? '');
    }
}
