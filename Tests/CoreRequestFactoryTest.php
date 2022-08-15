<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Tests;

use GuzzleHttp\ClientInterface;
use Kanti\ServerTiming\XClass\CoreRequestFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @coversDefaultClass \Kanti\ServerTiming\XClass\CoreRequestFactory
 */
class CoreRequestFactoryTest extends TestCase
{
    /**
     * @test
     * @covers ::getClient
     */
    public function getClientIsProtected(): void
    {
        $this->setUpTypo3ConfVars();

        $this->expectExceptionMessage('Call to protected method Kanti\ServerTiming\XClass\CoreRequestFactory::getClient()');
        (new CoreRequestFactory())->getClient();
    }

    /**
     * @test
     * @covers ::getClient
     */
    public function getClient(): void
    {
        $this->setUpTypo3ConfVars();

        $requestFactory = new ReflectionClass(CoreRequestFactory::class);
        $reflectionMethod = $requestFactory->getMethod('getClient');
        $reflectionMethod->setAccessible(true);
        $client = $reflectionMethod->invoke(new CoreRequestFactory());
        self::assertInstanceOf(ClientInterface::class, $client);
    }

    private function setUpTypo3ConfVars(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['HTTP'] = [
            'verify' => true,
            'handler' => [
                'testKey' => function () {

                },
            ],
        ];
    }

}
