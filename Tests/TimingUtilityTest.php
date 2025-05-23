<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Tests;

use Exception;
use Generator;
use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Service\ConfigService;
use Kanti\ServerTiming\Service\RegisterShutdownFunction\RegisterShutdownFunctionNoop;
use Kanti\ServerTiming\Service\SentryService;
use Kanti\ServerTiming\Service\SentryServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\HtmlResponse;
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
        $stopWatch = TimingUtility::getInstance()->stopWatchInternal('test');
        self::assertInstanceOf(StopWatch::class, $stopWatch);
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
    public function shutdown(string $expected, array $stopWatches, ?int $numberOfTimings, ?int $lengthOfDescription): void
    {
        $timingUtility = $this->getTestInstance($stopWatches);

        $container = new Container();
        $container->set(SentryService::class, null);
        GeneralUtility::setContainer($container);

        if ($numberOfTimings) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing']['number_of_timings'] = $numberOfTimings;
        }

        if ($lengthOfDescription) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['server_timing']['length_of_description'] = $lengthOfDescription;
        }

        $reflection = new ReflectionClass(TimingUtility::class);
        $isAlreadyShutdown = $reflection->getProperty('alreadyShutdown');
        self::assertFalse($isAlreadyShutdown->getValue($timingUtility));

        $response = $timingUtility->shutdown(ScriptResult::fromRequest(new ServerRequest(), new Response()));
        $result = $response?->getHeader('Server-Timing')[0];
        self::assertSame($expected, $result);

        self::assertTrue($isAlreadyShutdown->getValue($timingUtility));
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

        $stopWatch4 = new StopWatch('key4', 'short duration');
        $stopWatch4->startTime = 100003.0003;
        $stopWatch4->stopTime = 100004.0000;

        yield 'simple' => [
            '000;desc="key1 info";dur=1000.00,001;desc="key2 info for longer description";dur=11000.00,002;desc="key3 info";dur=21000.00,003;desc="key4 short duration";dur=999.70',
            'stopWatches' => [$stopWatch1, $stopWatch2, $stopWatch3, $stopWatch4],
            'numberOfTimings' => null,
            'lengthOfDescription' => null,
        ];
        yield 'numberOfTimings' => [
            '001;desc="key2 info for longer description";dur=11000.00,002;desc="key3 info";dur=21000.00',
            'stopWatches' => [$stopWatch1, $stopWatch2, $stopWatch3],
            'numberOfTimings' => 2,
            'lengthOfDescription' => null,
        ];
        yield 'lengthOfDescription' => [
            '000;desc="key1 info";dur=1000.00,001;desc="key2 info ";dur=11000.00,002;desc="key3 info";dur=21000.00',
            'stopWatches' => [$stopWatch1, $stopWatch2, $stopWatch3],
            'numberOfTimings' => null,
            'lengthOfDescription' => 10,
        ];
        yield 'unsorted_stop_watches' => [
            '000;desc="key2 info for longer description";dur=11000.00,002;desc="key3 info";dur=21000.00',
            'stopWatches' => [$stopWatch2, $stopWatch1, $stopWatch3],
            'numberOfTimings' => 2,
            'lengthOfDescription' => null,
        ];
        yield 'less_timings' => [
            '000;desc="key1 info";dur=1000.00',
            'stopWatches' => [$stopWatch1],
            'numberOfTimings' => 5,
            'lengthOfDescription' => null,
        ];
    }

    #[Test]
    public function sendSentryTrace(): void
    {
        $providedStopWatches = [];
        for ($i = 1; $i < 100; $i++) {
            $stopWatch = new StopWatch('key-' . $i, 'info');
            $stopWatch->startTime = 100001.0000 + $i;
            $providedStopWatches[] = $stopWatch;
        }

        $timingUtility = $this->getTestInstance($providedStopWatches);

        $container = new Container();
        $container->set(
            SentryServiceInterface::class,
            new class implements SentryServiceInterface {
                public function addSentryTraceHeaders(RequestInterface $request, StopWatch $stopWatch): RequestInterface
                {
                    return $request;
                }

                /**
                 * @param list<StopWatch> $stopWatches
                 */
                public function sendSentryTrace(ScriptResult $result, array $stopWatches): ?ResponseInterface
                {
                    $sortedWatches = $stopWatches;
                    usort($sortedWatches, static fn($a, $b): int => $b->stopTime <=> $a->stopTime);
                    TimingUtilityTest::assertSame($sortedWatches, $stopWatches);
                    TimingUtilityTest::assertNotNull($stopWatches[0]->stopTime);
                    return new HtmlResponse('');
                }
            }
        );
        GeneralUtility::setContainer($container);

        $timingUtility->shutdown(ScriptResult::fromRequest(new ServerRequest(), new Response()));
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

    /**
     * @param list<string> $timings
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('chunkStringArrayDataProvider')]
    public function chunkStringArray(array $timings, int $maxLength, array $expected): void
    {
        $reflection = new ReflectionClass(TimingUtility::class);
        $reflectionMethod = $reflection->getMethod('chunkStringArray');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->getTestInstance(), $timings, $maxLength);
        self::assertSame($expected, $result);
        self::assertIsList($result);
    }

    /**
     * @return Generator<string, array{timings: list<string>, maxLength: int, expected: list<string>}>
     */
    public static function chunkStringArrayDataProvider(): Generator
    {
        yield 'maxLength 1' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 1,
            'expected' => ['a', 'b', 'c', 'd', 'e', 'f'],
        ];
        yield 'maxLength 2' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 2,
            'expected' => ['a', 'b', 'c', 'd', 'e', 'f'],
        ];
        yield 'maxLength 3' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 3,
            'expected' => ['a,b', 'c,d', 'e,f'],
        ];
        yield 'maxLength 4' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 4,
            'expected' => ['a,b', 'c,d', 'e,f'],
        ];
        yield 'maxLength 5' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 5,
            'expected' => ['a,b,c', 'd,e,f'],
        ];
        yield 'maxLength 6' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 6,
            'expected' => ['a,b,c', 'd,e,f'],
        ];
        yield 'maxLength 7' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 7,
            'expected' => ['a,b,c,d', 'e,f'],
        ];
        yield 'maxLength 8' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 8,
            'expected' => ['a,b,c,d', 'e,f'],
        ];
        yield 'maxLength 9' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 9,
            'expected' => ['a,b,c,d,e', 'f'],
        ];
        yield 'maxLength 10' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 10,
            'expected' => ['a,b,c,d,e', 'f'],
        ];
        yield 'maxLength 11' => [
            'timings' => ['a', 'b', 'c', 'd', 'e', 'f'],
            'maxLength' => 11,
            'expected' => ['a,b,c,d,e,f'],
        ];
    }

    /**
     * @param StopWatch[] $stopWatches
     */
    private function getTestInstance(array $stopWatches = []): TimingUtility
    {
        $timingUtility = new TimingUtility(new RegisterShutdownFunctionNoop(), new ConfigService());
        $timingUtility::$isTesting = true;
        $reflection = new ReflectionClass($timingUtility);
        $reflection->getProperty('order')->setValue($timingUtility, $stopWatches);
        return $timingUtility;
    }
}
