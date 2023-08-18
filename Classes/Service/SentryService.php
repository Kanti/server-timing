<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service;

use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Pluswerk\Sentry\Service\Sentry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;
use TYPO3\CMS\Core\Http\HtmlResponse;

use function Sentry\continueTrace;
use function Sentry\getBaggage;
use function Sentry\getTraceparent;

final class SentryService
{
    public function __construct(private readonly ConfigService $configService)
    {
    }

    /**
     * @param StopWatch[] $stopWatches
     */
    public function sendSentryTrace(ScriptResult $result, array $stopWatches): ?ResponseInterface
    {
        if (!class_exists(SentrySdk::class)) {
            return $result->response;
        }

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();
        if (!$client && class_exists(Sentry::class)) {
            $client = Sentry::getInstance()->getClient();
        }

        if (!$client) {
            return $result->response;
        }

        $options = $client->getOptions();

        $forceTrace = $this->isForceTrace($result->request);

        $options->setTracesSampleRate((float)($forceTrace ?: $this->configService->tracesSampleRate() ?? $options->getTracesSampleRate()));
        $options->setEnableTracing($forceTrace ?: $this->configService->enableTracing() ?? $options->getEnableTracing());

        if ($result->isCli()) {
            $transactionContext = $this->createTransactionContextFromCli($result);
        } else {
            $transactionContext = $this->createTransactionContextFromRequest($result);
        }

        $transactionContext->setStartTimestamp($stopWatches[0]->startTime);

        $transaction = $hub->startTransaction($transactionContext);
        $hub->setSpan($transaction);

        /** @var non-empty-list<Span> $stack */
        $stack = [$transaction];
        foreach ($stopWatches as $stopWatch) {
            while (count($stack) > 1 && $stopWatch->stopTime > $stack[array_key_last($stack)]->getEndTimestamp()) {
                array_pop($stack);
            }

            $parent = $stack[array_key_last($stack)];
            $spanContext = new SpanContext();
            $spanContext->setOp($stopWatch->key);
            $spanContext->setStartTimestamp($stopWatch->startTime);
            $spanContext->setDescription($stopWatch->info);
            $span = $parent->startChild($spanContext);
            $span->finish($stopWatch->stopTime);
            $hub->setSpan($span);
            $stack[] = $span;
        }

        $hub->setSpan($transaction);

        $should = $options->shouldAttachStacktrace();
        $options->setAttachStacktrace(false);
        $transaction->finish($stopWatches[0]->stopTime);
        $options->setAttachStacktrace($should);

        return $this->addSentryTraceToResponse($result->response);
    }

    private function addSentryTraceToResponse(?ResponseInterface $response): ?ResponseInterface
    {
        if (!$response) {
            return null;
        }

        if (!str_starts_with($response->getHeaderLine('Content-Type'), 'text/html')) {
            return $response;
        }

        $stream = $response->getBody();
        $stream->rewind();

        $html = $stream->getContents();
        $position = stripos($html, '</head>') ?: stripos($html, '<body');
        if (!$position) {
            return $response;
        }

        $sentryMetaTags = sprintf('<meta name="sentry-trace" content="%s"/>', getTraceparent());
        $sentryMetaTags .= sprintf('<meta name="baggage" content="%s"/>', getBaggage());

        $html = substr($html, 0, $position) . $sentryMetaTags . substr($html, $position);

        return new HtmlResponse($html, $response->getStatusCode(), $response->getHeaders());
    }

    private function createTransactionContextFromRequest(ScriptResult $result): TransactionContext
    {
        $serverRequest = $result->request;
        assert($serverRequest instanceof ServerRequestInterface);

        $sentryTrace = $serverRequest->getHeaderLine('sentry-trace');
        $baggage = $serverRequest->getHeaderLine('baggage');

        // todo fix "A root transaction is missing. Transactions linked by a dashed line have been orphaned and cannot be directly linked to the root."
        // todo add sentry-trace and baggage header in guzzle client.
        if ($sentryTrace) {
            $transactionContext = continueTrace($sentryTrace, $baggage);
        } else {
            $transactionContext = new TransactionContext();
        }

        $transactionContext->setName($serverRequest->getMethod() . ' ' . $serverRequest->getUri());
        $transactionContext->setOp('typo3.request');
        $transactionContext->setData([
            'request.method' => $serverRequest->getMethod(),
            'request.query' => $serverRequest->getQueryParams(),
            'request.body' => $serverRequest->getParsedBody(),
            'request.headers' => $serverRequest->getHeaders(),
            'request.cookies' => $serverRequest->getCookieParams(),
            'request.url' => (string)$serverRequest->getUri(),
        ]);
        $statusCode = $result->response?->getStatusCode() ?? http_response_code();
        if (is_int($statusCode)) {
            $transactionContext->setStatus(SpanStatus::createFromHttpStatusCode($statusCode));
        }

        return $transactionContext;
    }

    private function createTransactionContextFromCli(ScriptResult $result): TransactionContext
    {
        $transactionContext = new TransactionContext();
        $transactionContext->setName(implode(' ', $_SERVER['argv']));
        $transactionContext->setOp('typo3.cli');
        if ($result->cliExitCode !== null) {
            $transactionContext->setStatus($result->cliExitCode ? SpanStatus::unknownError() : SpanStatus::ok());
        }

        return $transactionContext;
    }

    private function isForceTrace(?ServerRequestInterface $request): bool
    {
        if (!$request) {
            return false;
        }

        return isset($request->getCookieParams()['XDEBUG_PROFILE']);
    }
}
