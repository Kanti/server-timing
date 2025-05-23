<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Service;

use GuzzleHttp\Psr7\Uri;
use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use Pluswerk\Sentry\Service\Sentry as PluswerkSentry;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Sentry\ClientInterface;
use Sentry\SentrySdk;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Sentry\Tracing\Span;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\Transaction;
use Sentry\Tracing\TransactionContext;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function Sentry\continueTrace;
use function Sentry\getBaggage;
use function Sentry\getTraceparent;

final class SentryService implements SingletonInterface, SentryServiceInterface
{
    private ?Transaction $transaction = null;

    public function __construct(private readonly ConfigService $configService)
    {
    }

    public function addSentryTraceHeaders(RequestInterface $request, StopWatch $stopWatch): RequestInterface
    {
        $client = $this->getSentryClient();
        if (!$client) {
            return $request;
        }

        $parentSpan = SentrySdk::getCurrentHub()->getSpan();
        assert($parentSpan instanceof Span);
        $span = $parentSpan->startChild(new SpanContext());
        $stopWatch->span = $span;
        return $request
            ->withHeader('sentry-trace', $span->toTraceparent())
            ->withHeader('baggage', $span->toBaggage());
    }

    /**
     * @param StopWatch[] $stopWatches
     */
    public function sendSentryTrace(ScriptResult $result, array $stopWatches): ?ResponseInterface
    {
        $client = $this->getSentryClient($result->request);
        if (!$client) {
            return $result->response;
        }

        $transaction = $this->startTransaction();
        $transaction->setStartTimestamp($stopWatches[0]->startTime);
        $this->addResultToTransaction($result, $transaction);

        $hub = SentrySdk::getCurrentHub();

        /** @var non-empty-list<Span> $stack */
        $stack = [$transaction];
        foreach ($stopWatches as $stopWatch) {
            while (count($stack) > 1 && $stopWatch->stopTime > $stack[array_key_last($stack)]->getEndTimestamp()) {
                array_pop($stack);
            }

            if ($stopWatch->key === 'php') {
                continue;
            }

            $parent = $stack[array_key_last($stack)];
            if ($stopWatch->span) {
                $span = $stopWatch->span;
                $span->setParentSpanId($parent->getSpanId());
            } else {
                $span = $parent->startChild(new SpanContext());
            }

            $this->updateSpan($span, $stopWatch);
            $hub->setSpan($span);
            $stack[] = $span;
        }

        $hub->setSpan($transaction);

        $options = $client->getOptions();
        $should = $options->shouldAttachStacktrace();
        $options->setAttachStacktrace(false);
        $transaction->finish($stopWatches[0]->stopTime);
        $options->setAttachStacktrace($should);

        return $this->addSentryTraceToResponse($result->response);
    }

    private function getSentryClient(?ServerRequestInterface $serverRequest = null): ?ClientInterface
    {
        if (!class_exists(SentrySdk::class)) {
            return null;
        }

        $hub = SentrySdk::getCurrentHub();
        $client = $hub->getClient();
        if (!$client && class_exists(PluswerkSentry::class)) {
            $client = PluswerkSentry::getInstance()->getClient();
        }

        if (!$client) {
            return null;
        }

        $options = $client->getOptions();

        $forceTrace = $this->isTraceForced($serverRequest);

        $options->setTracesSampleRate((float)($forceTrace ?: $this->configService->tracesSampleRate() ?? $options->getTracesSampleRate()));
        $options->setEnableTracing($forceTrace ?: $this->configService->enableTracing() ?? $options->getEnableTracing());

        $this->startTransaction();

        return $client;
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

        return new HtmlResponse($html, $response->getStatusCode(), [...$response->getHeaders(), 'Content-Length' => strlen($html)]);
    }

    private function createTransactionContextFromCli(): TransactionContext
    {
        $transactionContext = new TransactionContext();
        $transactionContext->setName(implode(' ', $_SERVER['argv']));
        $transactionContext->setOp('typo3.cli');

        return $transactionContext;
    }

    private function createTransactionContextFromRequest(ServerRequestInterface $serverRequest): TransactionContext
    {
        $sentryTrace = $serverRequest->getHeaderLine('sentry-trace');
        $baggage = $serverRequest->getHeaderLine('baggage');

        if ($sentryTrace) {
            $transactionContext = continueTrace($sentryTrace, $baggage);
        } else {
            $transactionContext = new TransactionContext();
        }

        $transactionContext->setName($serverRequest->getMethod() . ' ' . $serverRequest->getUri());
        $transactionContext->setOp('http.server');
        $transactionContext->setData([
            'client.address' => GeneralUtility::getIndpEnv('REMOTE_ADDR'),
            'url.scheme' => $serverRequest->getUri()->getScheme(),
            'server.address' => $serverRequest->getUri()->getHost(),
            'server.port' => $serverRequest->getUri()->getPort(),
            'url.path' => $serverRequest->getUri()->getPath(),
            'url.query' => $serverRequest->getQueryParams(),
            'url.full' => (string)$serverRequest->getUri(),
            'request.method' => $serverRequest->getMethod(),
            'request.query' => $serverRequest->getQueryParams(),
            'request.body' => $serverRequest->getParsedBody(),
            'request.headers' => $serverRequest->getHeaders(),
            'request.cookies' => $serverRequest->getCookieParams(),
            'request.url' => (string)$serverRequest->getUri(),
            // TODO add body sizes
        ]);

        return $transactionContext;
    }

    private function addResultToTransaction(ScriptResult $result, Transaction $transaction): Transaction
    {
        if ($result->isCli()) {
            if ($result->cliExitCode !== null) {
                $transaction->setStatus($result->cliExitCode ? SpanStatus::unknownError() : SpanStatus::ok());
            }

            return $transaction;
        }

        $statusCode = $result->response?->getStatusCode() ?? http_response_code();
        if (is_int($statusCode)) {
            $transaction->setStatus(SpanStatus::createFromHttpStatusCode($statusCode));
        }

        return $transaction;
    }


    private function isTraceForced(?ServerRequestInterface $serverRequest): bool
    {
        $serverRequest = $this->getServerRequest($serverRequest);

        if (!$serverRequest) {
            return false;
        }

        return isset($serverRequest->getCookieParams()['XDEBUG_TRACE']);
    }

    private function startTransaction(): Transaction
    {
        if ($this->transaction) {
            return $this->transaction;
        }

        if (TimingUtility::IS_CLI) {
            $transactionContext = $this->createTransactionContextFromCli();
        } else {
            $serverRequest = $this->getServerRequest();
            assert($serverRequest instanceof ServerRequestInterface);
            $transactionContext = $this->createTransactionContextFromRequest($serverRequest);
        }


        $hub = SentrySdk::getCurrentHub();
        $this->transaction = $hub->startTransaction($transactionContext);
        $hub->setSpan($this->transaction);
        return $this->transaction;
    }

    private function updateSpan(Span $span, StopWatch $stopWatch): void
    {
        $span->setOp($stopWatch->key);
        $span->setStartTimestamp($stopWatch->startTime);

        $description = $stopWatch->info;
        if ($stopWatch->key === 'http.client') {
            $info = explode(' ', $stopWatch->info, 3);
            $uri = new Uri($info[1] ?? '');
            $partialUri = Uri::fromParts([
                'scheme' => $uri->getScheme(),
                'host' => $uri->getHost(),
                'port' => $uri->getPort(),
                'path' => $uri->getPath(),
            ]);
            $description = $info[0] . ' ' . $partialUri . ' ' . ($info[2] ?? '');
            $span->setData([
                'http.query' => $uri->getQuery(),
                'http.fragment' => $uri->getFragment(),
            ]);
        }

        $span->setDescription($description);
        $span->finish($stopWatch->stopTime);
    }

    private function getServerRequest(?ServerRequestInterface $serverRequest = null): ?ServerRequestInterface
    {
        $serverRequest ??= $GLOBALS['TYPO3_REQUEST'] ?? null;
        $serverRequest ??= TimingUtility::IS_CLI ? null : ServerRequestFactory::fromGlobals();
        return $serverRequest;
    }
}
