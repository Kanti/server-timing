<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Pluswerk\Sentry\Service\Sentry;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;

final class SentryUtility
{
    /**
     * @param StopWatch[] $stopWatches
     */
    public function sendSentryTrace(ScriptResult $result, array $stopWatches): void
    {
        if (!class_exists(SentrySdk::class)) {
            return;
        }

        if (!class_exists(ClientBuilder::class)) {
            return;
        }

        if (!class_exists(TransactionContext::class)) {
            return;
        }

        if (!class_exists(SpanContext::class)) {
            return;
        }

        if (!class_exists(Span::class)) {
            return;
        }

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();
        if (!$client && class_exists(Sentry::class)) {
            $client = Sentry::getInstance()->getClient();
        }

        if (!$client) {
            return;
        }

        $options = $client->getOptions();

        $options->setProfilesSampleRate(1.0); // TODO do not hard code!!!
        $options->setTracesSampleRate(1.0); // TODO do not hard code!!!
        $options->setEnableTracing(true); // TODO do not hard code!!!

        $client = (new ClientBuilder($options))->getClient();
        $hub->bindClient($client);

        $transactionContext = new TransactionContext();
        if ($result->isCli()) {
            $this->setContextFromCli($transactionContext, $result);
        } else {
            $this->setContextFromRequest($transactionContext, $result);
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

        $transaction->finish($stopWatches[0]->stopTime);
    }

    private function setContextFromRequest(TransactionContext $transactionContext, ScriptResult $result): void
    {
        $serverRequest = $result->request;
        assert($serverRequest instanceof ServerRequestInterface);
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
    }

    private function setContextFromCli(TransactionContext $transactionContext, ScriptResult $result): void
    {
        $transactionContext->setName(implode(' ', $_SERVER['argv']));
        $transactionContext->setOp('typo3.cli');
        if ($result->cliExitCode !== null) {
            $transactionContext->setStatus($result->cliExitCode ? SpanStatus::unknownError() : SpanStatus::ok());
        }
    }
}
