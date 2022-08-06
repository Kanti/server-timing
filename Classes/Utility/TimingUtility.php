<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use Kanti\ServerTiming\Dto\Time;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

final class TimingUtility
{
    /** @var Time[] */
    private static $order = [];
    /** @var array<string, Time> */
    private static $keyRef = [];
    /** @var array<string, \Closure> */
    private static $stopWatchStack = [];
    /** @var bool */
    private static $registered = false;

    public static function start(string $key, bool $isTotal = false): void
    {
        $s = self::stopWatch($key, $isTotal);
        if (isset(self::$stopWatchStack[$key])) {
            if (!$isTotal) {
                throw new \Exception('only one measurement at a time, use TimingUtility::stopWatch() for parallel measurements');
            }
        }
        self::$stopWatchStack[$key] = $s;
    }

    public static function end(string $key): void
    {
        if (!isset(self::$stopWatchStack[$key])) {
            throw new \Exception('where is no measurement with this key');
        }
        $stop = self::$stopWatchStack[$key];
        $stop();
    }

    public static function stopWatch(string $key, bool $isTotal = false): \Closure
    {
        if ($isTotal) {
            if (isset(self::$keyRef[$key])) {
                $time = self::$keyRef[$key];
            } else {
                $time = new Time();
                $time->key = $key;
                self::$keyRef[$key] = $time;
                self::$order[] = $time;
            }
        } else {
            $time = new Time();
            $time->key = $key;
            self::$order[] = $time;
        }
        $time->startTime[] = microtime(true);


        if (!self::$registered) {
            register_shutdown_function(static function () {
                self::shutdown();
            });
            self::$registered = true;
        }

        return static function () use ($time) {
            $time->stopTime[] = microtime(true);
        };
    }

    private static function shutdown(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        self::end('php');
        $timings = [];
        $keyCount = [];
        foreach (self::$order as $index => $time) {
            $keyCount[$time->key] = $keyCount[$time->key] ?? -1;
            $keyCount[$time->key]++;
            $singleTimes = array_filter(
                array_map(static function (float $startTime, ?float $endTime) {
                    if ($endTime === null) {
                        $endTime = microtime(true);
                    }
                    return ($endTime - $startTime) * 1000;
                }, $time->startTime, $time->stopTime)
            );
            $totalTime = array_sum($singleTimes);
            $count = count($time->startTime);
            $key = $time->key;
            if ($keyCount[$time->key]) {
                $key .= $keyCount[$time->key];
            }
            if ($count > 1) {
                $key .= ' count:' . $count;
            }
            $timings[] = self::timingString($index, $key, $totalTime);
            rsort($singleTimes);
            if (count($singleTimes) > 1) {
                foreach (array_slice($singleTimes, 0, 3) as $subIndex => $subTime) {
                    $timings[] = self::timingString($index, $time->key . ' top: ' . ($subIndex + 1), $subTime, $subIndex);
                }
            }
        }
        if (count($timings) > 70) {
            $timings = [self::timingString(0, 'To Many measurements ' . count($timings), 1.0)];
        }
        if ($timings) {
            header(sprintf('Server-Timing: %s', implode(',', $timings)), false);
        }
    }

    private static function timingString(int $index, string $key, float $duration, ?int $subIndex = null): string
    {
        $subIndexString = '';
        if ($subIndex !== null) {
            $subIndexString = '_' . str_pad((string)$subIndex, 3, '0');
        }
        return sprintf('%02d%s;desc="%s";dur=%0.2f', $index, $subIndexString, $key, $duration);
    }
}
