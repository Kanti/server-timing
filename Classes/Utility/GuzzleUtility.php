<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleUtility
{
    public static function getHandler(): Closure
    {
        return static function (callable $handler): Closure {
            return static function (RequestInterface $request, array $options) use ($handler): ResponseInterface {
                $str = 'guzzle ' . $request->getMethod();
                if ($request->getUri()) {
                    $str .= ' ' . $request->getUri()->getHost();
                }
                $stop = TimingUtility::stopWatch($str);
                $response = $handler($request, $options);
                $stop();
                return $response;
            };
        };
    }
}
