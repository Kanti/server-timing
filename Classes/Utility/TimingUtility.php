<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Kanti\ServerTiming\Dto\StopWatch;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TimingUtility
{
    /** @var TimingUtility|null */
    private static $instance = null;
    /** @var bool */
    private static $registered = false;
    /** @var bool|null */
    private static $isBackendUser = null;
    /** @var bool */
    private static $isCli = PHP_SAPI === 'cli';

    public static function getInstance(): TimingUtility
    {
        return self::$instance ??= new self();
    }

    /**
     * only for tests
     * @phpstan-ignore-next-line
     */
    private static function resetInstance(): void
    {
        self::$instance = null;
    }

    /** @var StopWatch[] */
    private $order = [];
    /** @var array<string, StopWatch> */
    private $stopWatchStack = [];

    public static function start(string $key, string $info = ''): void
    {
        self::getInstance()->startInternal($key, $info);
    }

    public function startInternal(string $key, string $info = ''): void
    {
        if (!$this->isActive()) {
            return;
        }
        $stop = $this->stopWatchInternal($key, $info);
        if (isset($this->stopWatchStack[$key])) {
            throw new \Exception('only one measurement at a time, use TimingUtility::stopWatch() for parallel measurements');
        }
        $this->stopWatchStack[$key] = $stop;
    }

    public static function end(string $key): void
    {
        self::getInstance()->endInternal($key);
    }

    public function endInternal(string $key): void
    {
        if (!$this->isActive()) {
            return;
        }
        if (!isset($this->stopWatchStack[$key])) {
            throw new \Exception('where is no measurement with this key');
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

        if ($this->isActive()) {
            if (!count($this->order)) {
                $phpStopWatch = new StopWatch('php', '');
                $phpStopWatch->startTime = $_SERVER["REQUEST_TIME_FLOAT"];
                $this->order[] = $phpStopWatch;
            }
            $this->order[] = $stopWatch;

            if (!self::$registered) {
                register_shutdown_function(static function () {
                    self::getInstance()->shutdown();
                });
                self::$registered = true;
            }
        }

        return $stopWatch;
    }

    private function shutdown(): void
    {
        if (!$this->isActive()) {
            return;
        }
        $timings = [];
        foreach ($this->combineIfToMuch($this->order) as $index => $time) {
            $timings[] = $this->timingString($index, trim($time->key . ' ' . $time->info), $time->getDuration());
        }
        if (count($timings) > 70) {
            $timings = [$this->timingString(0, 'To Many measurements ' . count($timings), 0.000001)];
        }
        if ($timings) {
            header(sprintf('Server-Timing: %s', implode(',', $timings)), false);
        }
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

    private function timingString(int $index, string $description, float $durationInSeconds): string
    {
        $description = substr($description, 0, 100);
        $description = str_replace(['\\', '"', ';'], ["_", "'", ","], $description);
        return sprintf('%03d;desc="%s";dur=%0.2f', $index, $description, $durationInSeconds * 1000);
    }

    public function isActive(): bool
    {
        if (self::$isCli) {
            return false;
        }
        if (self::$isBackendUser === false && Environment::getContext()->isProduction()) {
            return false;
        }
        return true;
    }

    /**
     * @internal
     */
    public function checkBackendUserStatus(): void
    {
        self::$isBackendUser = (bool)GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if (!$this->isActive()) {
            self::$instance = null;
        }
    }
}
