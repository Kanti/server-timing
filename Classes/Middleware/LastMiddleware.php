<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LastMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        TimingUtility::end('middleware Inward');
        TimingUtility::start('requestHandler');
        $response = $handler->handle($request);
        TimingUtility::end('requestHandler');
        TimingUtility::start('middleware Outward');
        return $response;
    }
}
