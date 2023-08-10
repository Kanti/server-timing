<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Dto;

use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequestFactory;

final class ScriptResult
{
    private function __construct(public readonly ?ServerRequestInterface $request, public readonly ?ResponseInterface $response, public readonly ?int $cliExitCode)
    {
    }

    public static function fromRequest(ServerRequestInterface $request, ?ResponseInterface $response = null): ScriptResult
    {
        return new ScriptResult($request, $response, null);
    }

    public static function fromCli(?int $cliExitCode = null): ScriptResult
    {
        return new ScriptResult(null, null, $cliExitCode);
    }

    public static function fromShutdown(): ScriptResult
    {
        if (TimingUtility::IS_CLI) {
            return self::fromCli();
        }

        return self::fromRequest($GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals());
    }

    public function isCli(): bool
    {
        return !$this->request;
    }
}
