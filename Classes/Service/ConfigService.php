<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service;

use Kanti\ServerTiming\Utility\TimingUtility;

final class ConfigService
{
    /** @var int */
    private const DEFAULT_STOP_WATCH_LIMIT = 100_000;

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

    private function getConfig(string $path): string
    {
        return (string)($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing'][$path] ?? '');
    }
}
