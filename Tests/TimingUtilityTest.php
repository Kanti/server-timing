<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Tests;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Kanti\ServerTiming\Utility\TimingUtility
 */
class TimingUtilityTest extends TestCase
{
    /**
     * @test
     * @covers ::stopWatch
     * @covers ::isActive
     * @covers \Kanti\ServerTiming\Dto\StopWatch
     */
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

    /**
     * @test
     * @covers ::stopWatch
     * @covers ::isActive
     * @covers \Kanti\ServerTiming\Dto\StopWatch
     */
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
     * @test
     * @covers ::timingString
     * @dataProvider dataProviderTimingString
     *
     * @param array{0:int, 1:string, 2:float} $args
     */
    public function timingString(string $expected, array $args): void
    {
        $reflection = new \ReflectionClass(TimingUtility::class);
        $reflectionMethod = $reflection->getMethod('timingString');
        $reflectionMethod->setAccessible(true);
        $result = $reflectionMethod->invoke(null, ...$args);
        self::assertEquals($expected, $result);
    }

    public function dataProviderTimingString(): \Generator
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
     * @test
     * @covers ::combineIfToMuch
     * @covers \Kanti\ServerTiming\Dto\StopWatch
     * @dataProvider dataProviderCombineIfToMuch
     *
     * @param StopWatch[] $expected
     * @param StopWatch[] $initalStopWatches
     */
    public function combineIfToMuch(array $expected, array $initalStopWatches): void
    {
        $reflection = new \ReflectionClass(TimingUtility::class);
        $initalStopWatches = array_map(static function (StopWatch $el) {
            return clone $el;
        }, $initalStopWatches);
        $reflectionMethod = $reflection->getMethod('combineIfToMuch');
        $reflectionMethod->setAccessible(true);
        $result = $reflectionMethod->invoke(null, $initalStopWatches);
        self::assertEqualsWithDelta($expected, $result, 0.00001);
    }

    public function dataProviderCombineIfToMuch(): \Generator
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
        $combined->stopTime = $stopWatchA->startTime + $stopWatchA->getDuration() + $stopWatchB->getDuration() + $stopWatchBig1->getDuration() + $stopWatchD->getDuration() + $stopWatchBig2->getDuration();
        yield 'combine:simple' => [
            [$combined, $stopWatchA, $stopWatchBig1, $stopWatchBig2],
            [$stopWatchA, $stopWatchB, $stopWatchBig1, $stopWatchD, $stopWatchBig2],
        ];
        $combined = new StopWatch('key', 'count:5');
        $combined->startTime = $stopWatchB->startTime;
        $combined->stopTime = $stopWatchB->startTime + $stopWatchA->getDuration() + $stopWatchB->getDuration() + $stopWatchBig1->getDuration() + $stopWatchD->getDuration() + $stopWatchBig2->getDuration();
        yield 'combine:keepOrder' => [
            [$stopWatchX, $combined, $stopWatchB, $stopWatchBig1, $stopWatchX2,  $stopWatchBig2],
            [$stopWatchX, $stopWatchB, $stopWatchBig1, $stopWatchX2, $stopWatchD, $stopWatchBig2, $stopWatchA],
        ];
    }
}
