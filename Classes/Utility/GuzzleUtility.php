<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use GuzzleHttp\Promise\FulfilledPromise;
use Psr\Http\Message\RequestInterface;

class GuzzleUtility
{
    public static function getHandler(): Closure
    {
        return static function (callable $handler): Closure {
            return static function (RequestInterface $request, array $options) use ($handler): FulfilledPromise {
                $info = $request->getMethod();
                if ($request->getUri()) {
                    $info .= ' ' . $request->getUri()->getHost();
                }
                $stop = TimingUtility::stopWatch('guzzle', $info);
                $response = $handler($request, $options);
                $stop();
                return $response;
            };
        };
    }
}
