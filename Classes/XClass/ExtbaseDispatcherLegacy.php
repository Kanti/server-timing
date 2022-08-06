<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\XClass;

use Kanti\ServerTiming\Utility\TimingUtility;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\ResponseInterface;

class ExtbaseDispatcherLegacy extends Dispatcher
{
    public function dispatch(RequestInterface $request, ResponseInterface $response)
    {
        $info = str_replace('\\', '_', $request->getControllerObjectName());
        if ($request instanceof Request) {
            $info .= '->' . $request->getControllerActionName();
        }
        $stop = TimingUtility::stopWatch('extbase', $info);
        parent::dispatch($request, $response);
        $stop();
    }
}
