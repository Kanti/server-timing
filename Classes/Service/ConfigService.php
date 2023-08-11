<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service;

final class ConfigService
{
    public function stopWatchLimit(): int
    {
        return (int)($this->getConfig('stop_watch_limit') ?? 100_000);
    }

    public function tracesSampleRate(): ?float
    {
        $tracesSampleRate = $this->getConfig('sentry_sample_rate');
        return $tracesSampleRate === null ? null : (float)$tracesSampleRate;
    }

    public function enableTracing(): ?bool
    {
        $tracesSampleRate = $this->tracesSampleRate();
        return $tracesSampleRate === null ? null : (bool)$tracesSampleRate;
    }

    private function getConfig(string $path): ?string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing'][$path] ?? null;
    }
}
