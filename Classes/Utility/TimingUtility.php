<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Exception;
use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SplObjectStorage;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TimingUtility
{
    private static ?TimingUtility $instance = null;

    private static bool $registered = false;

    /** @var bool */
    public const IS_CLI = PHP_SAPI === 'cli';

    private bool $alreadyShutdown = false;

    public static function getInstance(): TimingUtility
    {
        // to not use GeneralUtility::makeInstance( as this is maybe called to early in the stack)
        return self::$instance ??= new self();
    }

    /** @var StopWatch[] */
    private array $order = [];

    /** @var array<string, StopWatch> */
    private array $stopWatchStack = [];

    public static function start(string $key, string $info = ''): void
    {
        self::getInstance()->startInternal($key, $info);
    }

    public function startInternal(string $key, string $info = ''): void
    {
        if (!$this->shouldTrack()) {
            return;
        }

        $stop = $this->stopWatchInternal($key, $info);
        if (isset($this->stopWatchStack[$key])) {
            throw new Exception('only one measurement at a time, use TimingUtility::stopWatch() for parallel measurements');
        }

        $this->stopWatchStack[$key] = $stop;
    }

    public static function end(string $key): void
    {
        self::getInstance()->endInternal($key);
    }

    public function endInternal(string $key): void
    {
        if (!$this->shouldTrack()) {
            return;
        }

        if (!isset($this->stopWatchStack[$key])) {
            throw new Exception('where is no measurement with this key');
        }

        $stop = $this->stopWatchStack[$key];
        $stop();
        unset($this->stopWatchStack[$key]);
    }

    public static function stopWatch(string $key, string $info = ''): StopWatch
    {
        return self::getInstance()->stopWatchInternal($key, $info);
    }

    public function stopWatchInternal(string $key, string $info = ''): StopWatch
    {
        $stopWatch = new StopWatch($key, $info);

        if ($this->shouldTrack()) {
            if (!count($this->order)) {
                $phpStopWatch = new StopWatch('php', '');
                $phpStopWatch->startTime = $_SERVER["REQUEST_TIME_FLOAT"];
                $this->order[] = $phpStopWatch;
            }


            // TODO limit to x watches
            $this->order[] = $stopWatch;

            if (!self::$registered) {
                register_shutdown_function(static function (): void {
                    self::getInstance()->shutdown(ScriptResult::fromShutdown());
                });
                self::$registered = true;
            }
        }

        return $stopWatch;
    }

    public function shutdown(ScriptResult $result): ?ResponseInterface
    {
        if (!$this->shouldTrack()) {
            return $result->response;
        }

        $this->alreadyShutdown = true;


        foreach (array_reverse($this->order) as $stopWatch) {
            $stopWatch->stopIfNot();
        }

        GeneralUtility::makeInstance(SentryUtility::class)->sendSentryTrace($result, $this->order);

        if (!$this->shouldAddHeader()) {
            return $result->response;
        }

        $timings = [];
        foreach ($this->combineIfToMuch($this->order) as $index => $time) {
            $timings[] = $this->timingString($index, trim($time->key . ' ' . $time->info), $time->getDuration());
        }

        if (count($timings) > 70) {
            $timings = [$this->timingString(0, 'To Many measurements ' . count($timings), 0.000001)];
        }


        $headerString = implode(',', $timings);
        if (!$timings) {
            return $result->response;
        }

        $memoryUsage = $this->humanReadableFileSize(memory_get_peak_usage());
        if ($result->response) {
            return $result->response
                ->withAddedHeader('Server-Timing', $headerString)
                ->withAddedHeader('X-Max-Memory-Usage', $memoryUsage);
        }

        header('Server-Timing: ' . $headerString, false);
        header('X-Max-Memory-Usage: ' . $memoryUsage, false);
        return null;
    }

    private function humanReadableFileSize(int $size): string
    {
        $fileSizeNames = [" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB"];
        $i = floor(log($size, 1024));
        return $size ? round($size / (1024 ** $i), 2) . $fileSizeNames[$i] : '0 Bytes';
    }

    /**
     * @param StopWatch[] $initalStopWatches
     * @return StopWatch[]
     */
    private function combineIfToMuch(array $initalStopWatches): array
    {
        $elementsByKey = [];
        foreach ($initalStopWatches as $stopWatch) {
            if (!isset($elementsByKey[$stopWatch->key])) {
                $elementsByKey[$stopWatch->key] = [];
            }

            $elementsByKey[$stopWatch->key][] = $stopWatch;
        }

        $keepStopWatches = new SplObjectStorage();

        $insertBefore = [];
        foreach ($elementsByKey as $key => $stopWatches) {
            $count = count($stopWatches);
            if ($count <= 4) {
                foreach ($stopWatches as $stopWatch) {
                    $keepStopWatches->attach($stopWatch);
                }

                continue;
            }

            $first = $stopWatches[0];
            $sum = array_sum(
                array_map(
                    static fn(StopWatch $stopWatch): float => $stopWatch->getDuration(),
                    $stopWatches
                )
            );
            $insertBefore[$key] = new StopWatch($key, 'count:' . $count);
            $insertBefore[$key]->startTime = $first->startTime;
            $insertBefore[$key]->stopTime = $insertBefore[$key]->startTime + $sum;

            usort($stopWatches, static fn(StopWatch $a, StopWatch $b): int => $b->getDuration() <=> $a->getDuration());

            $biggestStopWatches = array_slice($stopWatches, 0, 3);
            foreach ($biggestStopWatches as $stopWatch) {
                $keepStopWatches->attach($stopWatch);
            }
        }

        $result = [];
        foreach ($initalStopWatches as $stopWatch) {
            if (isset($insertBefore[$stopWatch->key])) {
                $result[] = $insertBefore[$stopWatch->key];
                unset($insertBefore[$stopWatch->key]);
            }

            if (!$keepStopWatches->contains($stopWatch)) {
                continue;
            }

            $result[] = $stopWatch;
        }

        return $result;
    }

    private function timingString(int $index, string $description, float $durationInSeconds): string
    {
        $description = substr($description, 0, 100);
        $description = str_replace(['\\', '"', ';', "\r", "\n"], ["_", "'", ",", "", ""], $description);
        return sprintf('%03d;desc="%s";dur=%0.2f', $index, $description, $durationInSeconds * 1000);
    }

    public function shouldAddHeader(): bool
    {
        if (self::IS_CLI) {
            return false;
        }

        if ($this->isBackendUser()) {
            return true;
        }

        return !Environment::getContext()->isProduction();
    }

    public function shouldTrack(): bool
    {
        return !$this->alreadyShutdown;
    }

    private function isBackendUser(): bool
    {
        return (bool)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
    }
}
