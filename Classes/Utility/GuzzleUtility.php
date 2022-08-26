<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

class GuzzleUtility
{
    public static function getHandler(): Closure
    {
        return static function (callable $handler): Closure {
            return static function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
                $info = $request->getMethod() . ' ' . $request->getUri()->__toString();
                $stop = TimingUtility::stopWatch('guzzle', $info);
                $response = $handler($request, $options);
                $stop();
                return $response;
            };
        };
    }
}
