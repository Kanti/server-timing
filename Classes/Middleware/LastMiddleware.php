<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LastMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        TimingUtility::getInstance()->checkBackendUserStatus();
        $stop = $request->getAttribute('server-timing:middleware:inward');
        if ($stop instanceof StopWatch) {
            $stop();
        }
        $request = $request->withoutAttribute('server-timing:middleware:inward');
        $stop = TimingUtility::stopWatch('requestHandler');
        $response = $handler->handle($request);
        $stop();
        // @phpstan-ignore-next-line
        $response->stopWatch = TimingUtility::stopWatch('middleware', 'Outward');
        return $response;
    }
}
