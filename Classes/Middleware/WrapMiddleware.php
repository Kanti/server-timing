<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class WrapMiddleware implements RequestHandlerInterface
{
    private static ?StopWatch $middlewareIn = null;

    private static ?StopWatch $middlewareOut = null;

    private bool $isFirst = false;

    public function __construct(
        private readonly RequestHandlerInterface $requestHandler,
        private readonly string $info,
        private readonly bool $isKernel = false,
    ) {
    }

    public function isFirst(): void
    {
        $this->isFirst = true;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        self::$middlewareIn?->stopIfNot();
        self::$middlewareIn = TimingUtility::stopWatch($this->isKernel ? 'requestHandler' : 'middleware.in', $this->info);

        if ($this->isKernel) {
            TimingUtility::end('middleware.in.total');
        }

        $response = $this->requestHandler->handle($request);

        if ($this->isKernel) {
            TimingUtility::start('middleware.out.total');
        }

        // if it was the requestHandler:
        self::$middlewareIn?->stopIfNot();
        self::$middlewareIn = null;

        $stopWatch = self::$middlewareOut;
        if ($stopWatch) {
            $stopWatch->stop();
            $stopWatch->info = $this->info;
        }

        if (!$this->isFirst) {
            self::$middlewareOut = TimingUtility::stopWatch('middleware.out');
        }

        return $response;
    }
}
