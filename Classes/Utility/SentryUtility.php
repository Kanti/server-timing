<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use Kanti\ServerTiming\Dto\StopWatch;
use Pluswerk\Sentry\Service\Sentry;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;

final class SentryUtility
{
    /**
     * @param StopWatch[] $stopWatches
     */
    public function sendSentryTrace(ServerRequestInterface $request, array $stopWatches): void
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
        $transactionContext->setName($request->getMethod() . ' ' . $request->getUri());
        $transactionContext->setOp('typo3.request');
        $transactionContext->setData([
            'request.method' => $request->getMethod(),
            'request.query' => $request->getQueryParams(),
            'request.body' => $request->getParsedBody(),
            'request.headers' => $request->getHeaders(),
            'request.cookies' => $request->getCookieParams(),
            'request.url' => (string)$request->getUri(),
        ]);
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

        $transaction->finish($stopWatches[0]->stopTime);
    }
}
