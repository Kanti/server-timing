<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Middleware;

use Exception;
use InvalidArgumentException;
use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\MiddlewareDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use const false;
use const false as false1;
use const false as false2;

final class XClassMiddlewareDispatcher extends MiddlewareDispatcher
{
    protected function seedMiddlewareStack(RequestHandlerInterface $kernel): void
    {
        $this->tip = new WrapMiddleware($kernel, '', true);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $stop = TimingUtility::stopWatch('bootstrap');
        $stop->startTime = $_SERVER["REQUEST_TIME_FLOAT"];
        $stop->stop();
        $request = $request->withAttribute('middleware.in.total', TimingUtility::stopWatch('middleware.in.total'));
        if ($this->tip instanceof WrapMiddleware) {
            $this->tip->isFirst();
        }

        try {
            $response = parent::handle($request);
        } catch (ImmediateResponseException $immediateResponseException) {
            $response = $immediateResponseException->getResponse();
        }

        try {
            TimingUtility::end('middleware.out.total');
        } catch (Exception) {
        }

        return TimingUtility::getInstance()->shutdown(ScriptResult::fromRequest($request, $response)) ?? $response;
    }

    public function add(MiddlewareInterface $middleware): void
    {
        parent::add($middleware);

        $this->tip = new WrapMiddleware($this->tip, $middleware::class);
    }

    public function lazy(string $middleware): void
    {
        parent::lazy($middleware);

        $this->tip = new WrapMiddleware($this->tip, $middleware);
    }
}
