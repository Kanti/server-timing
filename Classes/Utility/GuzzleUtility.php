<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Kanti\ServerTiming\Service\SentryService;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class GuzzleUtility
{
    public static function getHandler(): ?Closure
    {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/typo3/module/system/config')) {
            // fix bug in Configuration Backend module
            return null;
        }

        // initialize early: so the spatie/async with staticfilecache doesn't kill the process
        // there is the problem that it has a subprocess where the Container is not initialized fully
        $timingUtility = TimingUtility::getInstance();
        $sentryService = GeneralUtility::makeInstance(SentryService::class);

        return static fn(callable $handler): Closure => static function (RequestInterface $request, array $options) use ($sentryService, $timingUtility, $handler): PromiseInterface {
            $info = $request->getMethod() . ' ' . $request->getUri()->__toString();
            $stop = $timingUtility->stopWatchInternal('http.client', $info);
            $request = $sentryService->addSentryTraceHeaders($request, $stop);

            $handlerPromiseCallback = static function ($responseOrException) use ($request, $stop) {
                $response = null;
                if ($responseOrException instanceof ResponseInterface) {
                    $response = $responseOrException;
                } elseif ($responseOrException instanceof GuzzleRequestException) {
                    $response = $responseOrException->getResponse();
                }

                $stop->stopIfNot();
                if ($response) {
                    $stop->info = $request->getMethod() . ' ' . $response->getStatusCode() . ' ' . $request->getUri()->__toString();
                }

                if ($responseOrException instanceof \Throwable) {
                    throw $responseOrException;
                }

                return $responseOrException;
            };
            try {
                return $handler($request, $options)->then($handlerPromiseCallback, $handlerPromiseCallback);
            } finally {
                $stop->stopIfNot();
            }
        };
    }
}
