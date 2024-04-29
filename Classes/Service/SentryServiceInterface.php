<?php

namespace Kanti\ServerTiming\Service;

use Kanti\ServerTiming\Dto\ScriptResult;
use Kanti\ServerTiming\Dto\StopWatch;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface SentryServiceInterface
{
    public function addSentryTraceHeaders(RequestInterface $request, StopWatch $stopWatch): RequestInterface;

    /**
     * @param StopWatch[] $stopWatches
     */
    public function sendSentryTrace(ScriptResult $result, array $stopWatches): ?ResponseInterface;
}
