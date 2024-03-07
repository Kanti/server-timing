<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Tests;

use Generator;
use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Service\ConfigService;
use Kanti\ServerTiming\Service\RegisterShutdownFunction\RegisterShutdownFunctionNoop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[CoversClass(TimingUtility::class)]
#[CoversClass(StopWatch::class)]
#[CoversClass(ConfigService::class)]
#[CoversClass(ScriptResult::class)]
#[CoversClass(RegisterShutdownFunctionNoop::class)]
final class TimingUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        GeneralUtility::setSingletonInstance(TimingUtility::class, $this->getTestInstance());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing']);
        GeneralUtility::resetSingletonInstances([]);
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
        TimingUtility::getInstance()->stopWatchInternal('test');
        self::assertTrue(true, 'isCallable');
    }

    #[Test]
    public function stopWatchFirstIsAlwaysPhp(): void
    {
        $timingUtility = $this->getTestInstance();
        $timingUtility->stopWatchInternal('test');

        $watches = $timingUtility->getStopWatches();
        self::assertCount(2, $watches);
        self::assertSame('php', $watches[0]->key);
        self::assertSame('test', $watches[1]->key);
    }

    #[Test]
    public function stopWatchLimit(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing']['stop_watch_limit'] = 3;
        $timingUtility = $this->getTestInstance();
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');

        $watches = $timingUtility->getStopWatches();
        self::assertCount(3, $watches);
        self::assertSame('php', $watches[0]->key);
        self::assertSame('test', $watches[1]->key);
        self::assertSame('test', $watches[2]->key);
    }

    #[Test]
    public function didRegisterShutdownFunctionOnce(): void
    {
        $timingUtility = new TimingUtility($registerShutdownFunction = new RegisterShutdownFunctionNoop(), new ConfigService());
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        $timingUtility->stopWatchInternal('test');
        self::assertSame(1, $registerShutdownFunction->callCount);
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

        $result = $reflectionMethod->invoke($this->getTestInstance(), ...$args);
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

    #[Test]
    #[DataProvider('dataProviderHumanReadableFileSize')]
    public function testHumanReadableFileSize(string $expected, int $size): void
    {
        $reflection = new ReflectionClass(TimingUtility::class);
        $reflectionMethod = $reflection->getMethod('humanReadableFileSize');

        $result = $reflectionMethod->invoke($this->getTestInstance(), $size);
        self::assertSame($expected, $result);
    }

    public static function dataProviderHumanReadableFileSize(): Generator
    {
        yield 'simple' => [
            '48.89 MB',
            51268468,
        ];
        yield 'big' => [
            '1 EB',
            1024 ** 6,
        ];
    }

    /**
     * @param StopWatch[] $stopWatches
     */
    #[Test]
    #[DataProvider('dataProviderShutdown')]
    public function shutdown(string $expected, array $stopWatches, ?int $numberOfTimings, ?int $lengthOfDesccription): void
    {
        $timingUtility = $this->getTestInstance();
        $timingUtility->setStopWatches($stopWatches);
        GeneralUtility::setContainer(new Container());
        if ($numberOfTimings) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing']['number_of_timings'] = $numberOfTimings;
        }

        if ($lengthOfDesccription) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing']['length_of_description'] = $lengthOfDesccription;
        }

        $response = $timingUtility->shutdown(ScriptResult::fromRequest(new ServerRequest(), new Response()));
        $result = $response?->getHeader('Server-Timing')[0];
        self::assertSame($expected, $result);
    }

    public static function dataProviderShutdown(): Generator
    {
        $stopWatch1 = new StopWatch('key1', 'info');
        $stopWatch1->startTime = 100003.0003;
        $stopWatch1->stopTime = 100004.0003;
        $stopWatch2 = new StopWatch('key2', 'info for longer description');
        $stopWatch2->startTime = 100004.0004;
        $stopWatch2->stopTime = 100015.0004;
        $stopWatch3 = new StopWatch('  key3', 'info');
        $stopWatch3->startTime = 100004.0004;
        $stopWatch3->stopTime = 100025.0004;

        yield 'simple' => [
            '000;desc="key1 info 1";dur=1000.00,001;desc="key2 info for longer description 11";dur=11000.00,002;desc="key3 info 21";dur=21000.00',
            'stopWatches' => [$stopWatch1, $stopWatch2, $stopWatch3],
            'number_of_timings' => null,
            'length_of_description' => null,
        ];
        yield 'number_of_timings' => [
            '001;desc="key2 info for longer description 11";dur=11000.00,002;desc="key3 info 21";dur=21000.00',
            'stopWatches' => [$stopWatch1, $stopWatch2, $stopWatch3],
            'number_of_timings' => 2,
            'length_of_description' => null,
        ];
        yield 'length_of_description' => [
            '000;desc="key1 info ";dur=1000.00,001;desc="key2 info ";dur=11000.00,002;desc="key3 info ";dur=21000.00',
            'stopWatches' => [$stopWatch1, $stopWatch2, $stopWatch3],
            'number_of_timings' => null,
            'length_of_description' => 10,
        ];
        yield 'unsorted_stop_watches' => [
            '000;desc="key2 info for longer description 11";dur=11000.00,002;desc="key3 info 21";dur=21000.00',
            'stopWatches' => [$stopWatch2, $stopWatch1, $stopWatch3],
            'number_of_timings' => 2,
            'length_of_description' => null,
        ];
        yield 'less_timings' => [
            '000;desc="key1 info 1";dur=1000.00',
            'stopWatches' => [$stopWatch1],
            'number_of_timings' => 5,
            'length_of_description' => null,
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

        $result = $reflectionMethod->invoke($this->getTestInstance(), $initalStopWatches);
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

        $timingUtility = $this->getTestInstance();

        $isAlreadyShutdown->setValue($timingUtility, false);
        self::assertTrue($timingUtility->shouldTrack());

        $isAlreadyShutdown->setValue($timingUtility, true);
        self::assertFalse($timingUtility->shouldTrack());
    }

    private function getTestInstance(): TimingUtility
    {
        $timingUtility = new TimingUtility(new RegisterShutdownFunctionNoop(), new ConfigService());
        $timingUtility::$isTesting = true;
        return $timingUtility;
    }
}
