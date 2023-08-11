<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Tests;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(TimingUtility::class)]
#[CoversClass(StopWatch::class)]
final class TimingUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        // do not register a real shutdown function:
        TimingUtility::getInstance(static fn(): int => 0);
    }

    #[Test]
    public function getInstance(): void
    {
        self::assertInstanceOf(TimingUtility::class, $firstCall = TimingUtility::getInstance());
        self::assertSame($firstCall, TimingUtility::getInstance());
    }

    #[Test]
    public function stopWatch(): void
    {
        $stopWatch = TimingUtility::stopWatch('testStopWatch');
        self::assertNull($stopWatch->stopTime);
        $stopWatch();
        $stopTime = $stopWatch->stopTime;
        self::assertIsFloat($stopTime);
        self::assertIsFloat($stopWatch->getDuration());
        self::assertSame($stopTime, $stopWatch->stopTime);
        self::assertGreaterThan(0, $stopWatch->getDuration());
        self::assertLessThan(1, $stopWatch->getDuration());
    }

    #[Test]
    public function stopWatchStop(): void
    {
        $stopWatch = TimingUtility::stopWatch('testStopWatch');
        self::assertNull($stopWatch->stopTime);
        $stopWatch->stop();
        $stopTime = $stopWatch->stopTime;
        self::assertIsFloat($stopTime);
        self::assertIsFloat($stopWatch->getDuration());
        self::assertSame($stopTime, $stopWatch->stopTime);
        self::assertGreaterThan(0.0, $stopWatch->getDuration());
        self::assertLessThan(1.0, $stopWatch->getDuration());
    }

    #[Test]
    public function stopWatchStopIfNot(): void
    {
        $stopWatch = TimingUtility::stopWatch('testStopWatch');
        self::assertNull($stopWatch->stopTime);
        $stopWatch->stopIfNot();
        self::assertGreaterThan(0.0, $stopWatch->stopTime);
        $stopWatch->stopTime = 0.0;
        $stopWatch->stopIfNot();
        self::assertSame(0.0, $stopWatch->stopTime);
    }

    #[Test]
    public function stopWatchInternal(): void
    {
        (new TimingUtility(static fn(): int => 0))->stopWatchInternal('test');
        self::assertTrue(true, 'isCallable');
    }

    #[Test]
    public function stopWatchFirstIsAlwaysPhp(): void
    {
        $timingUtility = new TimingUtility(static fn(): int => 0);
        $timingUtility->stopWatchInternal('test');

        $watches = $timingUtility->getStopWatches();
        self::assertCount(2, $watches);
        self::assertSame('php', $watches[0]->key);
        self::assertSame('test', $watches[1]->key);
    }

    #[Test]
    public function didRegisterShutdownFunctionOnce(): void
    {
        $called = 0;
        $timingUtility = new TimingUtility(static function ($callback) use (&$called): void {
            self::assertIsCallable($callback);
            $called++;
        });
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        self::assertSame(1, $called);
    }

    #[Test]
    public function stopWatchGetDuration(): void
    {
        $stopWatch = TimingUtility::stopWatch('testStopWatch');
        self::assertNull($stopWatch->stopTime);
        self::assertIsFloat($stopWatch->getDuration());
        self::assertIsFloat($stopWatch->stopTime);
        self::assertGreaterThan(0, $stopWatch->getDuration());
        self::assertLessThan(1, $stopWatch->getDuration());
    }

    /**
     * @param array{0:int, 1:string, 2:float} $args
     */
    #[Test]
    #[DataProvider('dataProviderTimingString')]
    public function timingString(string $expected, array $args): void
    {
        $reflection = new ReflectionClass(TimingUtility::class);
        $reflectionMethod = $reflection->getMethod('timingString');

        $result = $reflectionMethod->invoke(new TimingUtility(static fn(): int => 0), ...$args);
        self::assertSame($expected, $result);
    }

    public static function dataProviderTimingString(): Generator
    {
        yield 'simple' => [
            '000;desc="key";dur=12210.00',
            [0, 'key', 12.21],
        ];
        yield 'toLong' => [
            '000;desc="this key veryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVer";dur=12210.00',
            [
                0,
                'this key veryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryVeryLong',
                12.21,
            ],
        ];
        yield 'specialChar' => [
            '000;desc=",_\'";dur=12219.88',
            [0, ';\\"', 12.21987654],
        ];
    }

    /**
     * @param StopWatch[] $expected
     * @param StopWatch[] $initalStopWatches
     */
    #[Test]
    #[DataProvider('dataProviderCombineIfToMuch')]
    public function combineIfToMuch(array $expected, array $initalStopWatches): void
    {
        $reflection = new ReflectionClass(TimingUtility::class);
        $initalStopWatches = array_map(static fn(StopWatch $el): StopWatch => clone $el, $initalStopWatches);
        $reflectionMethod = $reflection->getMethod('combineIfToMuch');

        $result = $reflectionMethod->invoke(new TimingUtility(static fn(): int => 0), $initalStopWatches);
        self::assertEqualsWithDelta($expected, $result, 0.00001);
    }

    public static function dataProviderCombineIfToMuch(): Generator
    {
        $stopWatchX = new StopWatch('x', 'info');
        $stopWatchX->startTime = 100001.0001;
        $stopWatchX->stopTime = 100002.0001;
        $stopWatchX2 = new StopWatch('x', 'info');
        $stopWatchX2->startTime = 100002.0002;
        $stopWatchX2->stopTime = 100003.0002;
        $stopWatchA = new StopWatch('key', 'info');
        $stopWatchA->startTime = 100003.0003;
        $stopWatchA->stopTime = 100004.0003;
        $stopWatchB = new StopWatch('key', 'info');
        $stopWatchB->startTime = 100004.0004;
        $stopWatchB->stopTime = 100005.0004;
        $stopWatchBig1 = new StopWatch('key', 'info');
        $stopWatchBig1->startTime = 100005.0005;
        $stopWatchBig1->stopTime = 100106.0005;
        $stopWatchD = new StopWatch('key', 'info');
        $stopWatchD->startTime = 100006.0006;
        $stopWatchD->stopTime = 100007.0006;
        $stopWatchBig2 = new StopWatch('key', 'info');
        $stopWatchBig2->startTime = 100007.0007;
        $stopWatchBig2->stopTime = 100108.0007;

        yield 'empty' => [
            [],
            [],
        ];
        yield 'simple' => [
            [$stopWatchA],
            [$stopWatchA],
        ];
        yield 'keepsOrder' => [
            [$stopWatchA, $stopWatchB],
            [$stopWatchA, $stopWatchB],
        ];
        yield 'nearlyCombine' => [
            [$stopWatchA, $stopWatchB, $stopWatchBig1, $stopWatchD],
            [$stopWatchA, $stopWatchB, $stopWatchBig1, $stopWatchD],
        ];
        $combined = new StopWatch('key', 'count:5');
        $combined->startTime = $stopWatchA->startTime;
        $combined->stopTime = $stopWatchA->startTime
            + $stopWatchA->getDuration()
            + $stopWatchB->getDuration()
            + $stopWatchBig1->getDuration()
            + $stopWatchD->getDuration()
            + $stopWatchBig2->getDuration();
        yield 'combine:simple' => [
            [$combined, $stopWatchA, $stopWatchBig1, $stopWatchBig2],
            [$stopWatchA, $stopWatchB, $stopWatchBig1, $stopWatchD, $stopWatchBig2],
        ];
        $combined = new StopWatch('key', 'count:5');
        $combined->startTime = $stopWatchB->startTime;
        $combined->stopTime = $stopWatchB->startTime
            + $stopWatchA->getDuration()
            + $stopWatchB->getDuration()
            + $stopWatchBig1->getDuration()
            + $stopWatchD->getDuration()
            + $stopWatchBig2->getDuration();
        yield 'combine:keepOrder' => [
            [$stopWatchX, $combined, $stopWatchB, $stopWatchBig1, $stopWatchX2, $stopWatchBig2],
            [$stopWatchX, $stopWatchB, $stopWatchBig1, $stopWatchX2, $stopWatchD, $stopWatchBig2, $stopWatchA],
        ];
    }

    #[Test]
    public function shouldTrack(): void
    {
        $reflection = new ReflectionClass(TimingUtility::class);
        $isAlreadyShutdown = $reflection->getProperty('alreadyShutdown');

        $timingUtility = new TimingUtility(static fn(): int => 0);

        $isAlreadyShutdown->setValue($timingUtility, false);
        self::assertTrue($timingUtility->shouldTrack());

        $isAlreadyShutdown->setValue($timingUtility, true);
        self::assertFalse($timingUtility->shouldTrack());
    }
}
