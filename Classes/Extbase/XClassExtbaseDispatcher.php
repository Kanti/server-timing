<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Extbase;

use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

final class XClassExtbaseDispatcher extends Dispatcher
{
    public function dispatch(RequestInterface $request): ResponseInterface
    {
        $info = str_replace('\\', '_', $request->getControllerObjectName());
        if ($request instanceof Request) {
            $info .= '->' . $request->getControllerActionName();
        }

        $stop = TimingUtility::stopWatch('extbase', $info);
        $response = parent::dispatch($request);
        $stop();
        return $response;
    }
}
