<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\Http\ServerRequestFactory;

final class GuzzleUtility
{
    public static function getHandler(): ?Closure
    {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/typo3/module/system/config')) {
            // fix bug in Configuration Backend module
            return null;
        }

        return static fn(callable $handler): Closure => static function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $info = $request->getMethod() . ' ' . $request->getUri()->__toString();
            $stop = TimingUtility::stopWatch('guzzle', $info);
            try {
                return $handler($request, $options);
            } finally {
                $stop();
            }
        };
    }
}
