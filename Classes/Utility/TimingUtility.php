<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use Kanti\ServerTiming\Dto\StopWatch;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

final class TimingUtility
{
    /** @var StopWatch[] */
    private static $order = [];
    /** @var array<string, StopWatch> */
    private static $stopWatchStack = [];
    /** @var bool */
    private static $registered = false;
    /** @var bool|null */
    private static $isBackendUser = null;

    public static function start(string $key, string $info = ''): void
    {
        if (!self::isActive()) {
            return;
        }
        $s = self::stopWatch($key, $info);
        if (isset(self::$stopWatchStack[$key])) {
            throw new \Exception('only one measurement at a time, use TimingUtility::stopWatch() for parallel measurements');
        }
        self::$stopWatchStack[$key] = $s;
    }

    public static function end(string $key): void
    {
        if (!self::isActive()) {
            return;
        }
        if (!isset(self::$stopWatchStack[$key])) {
            throw new \Exception('where is no measurement with this key');
        }
        $stop = self::$stopWatchStack[$key];
        $stop();
        unset(self::$stopWatchStack[$key]);
    }

    public static function stopWatch(string $key, string $info = ''): StopWatch
    {
        $stopWatch = new StopWatch($key, $info);

        if (self::isActive()) {

            self::$order[] = $stopWatch;

            if (!self::$registered) {
                register_shutdown_function(static function () {
                    self::shutdown();
                });
                self::$registered = true;
            }
        }

        return $stopWatch;
    }

    private static function shutdown(): void
    {
        if (!self::isActive()) {
            return;
        }
        self::end('php');
        $timings = [];
        foreach (self::combineIfToMuch(self::$order) as $index => $time) {
            $timings[] = self::timingString($index, trim($time->key . ' ' . $time->info), $time->getDuration());
        }
        if (count($timings) > 70) {
            $timings = [self::timingString(0, 'To Many measurements ' . count($timings), 0.000001)];
        }
        if ($timings) {
            header(sprintf('Server-Timing: %s', implode(',', $timings)), false);
        }
    }

    /**
     * @param StopWatch[] $initalStopWatches
     * @return StopWatch[]
     */
    private static function combineIfToMuch(array $initalStopWatches): array
    {
        $elementsByKey = [];
        $removeInfo = false; // TODO add option
        foreach ($initalStopWatches as $stopWatch) {
            if (!isset($elementsByKey[$stopWatch->key])) {
                $elementsByKey[$stopWatch->key] = [];
            }
            if ($removeInfo && $stopWatch->info) {
                $stopWatch->info = '<censored>';
            }
            $elementsByKey[$stopWatch->key][] = $stopWatch;
        }
        $keepStopWatches = new \SplObjectStorage();

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
                    static function (StopWatch $stopWatch) {
                        return $stopWatch->getDuration();
                    },
                    $stopWatches
                )
            );
            $insertBefore[$key] = new StopWatch($key, 'count:' . $count);
            $insertBefore[$key]->startTime = $first->startTime;
            $insertBefore[$key]->stopTime = $insertBefore[$key]->startTime + $sum;

            usort($stopWatches, static function (StopWatch $a, StopWatch $b) {
                return $b->getDuration() <=> $a->getDuration();
            });

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

    private static function timingString(int $index, string $description, float $durationInSeconds): string
    {
        $description = substr($description, 0, 100);
        $description = str_replace(['\\', '"', ';'], ["_", "'", ","], $description);
        return sprintf('%03d;desc="%s";dur=%0.2f', $index, $description, $durationInSeconds * 1000);
    }

    private static function isActive(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        if (self::$isBackendUser === false && Environment::getContext()->isProduction()) {
            return false;
        }
        return true;
    }

    public static function checkBackendUserStatus(): void
    {
        self::$isBackendUser = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if (!self::isActive()) {
            self::$order = [];
            self::$stopWatchStack = [];
        }
    }
}
