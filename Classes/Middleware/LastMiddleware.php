<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LastMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $stopWatch = $request->getAttribute('server-timing:middleware:inward');
        $stopWatch?->stop();

        $request = $request->withoutAttribute('server-timing:middleware:inward');
        $stopWatch = TimingUtility::stopWatch('requestHandler');
        try {
            $response = $handler->handle($request);
        } finally {
            $stopWatch();
            FirstMiddleware::$stopWatchOutward = TimingUtility::stopWatch('middleware', 'Outward');
        }

        return $response;
    }
}
