<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\XClass;

use Kanti\ServerTiming\Utility\TimingUtility;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Request;

class ExtbaseDispatcher extends Dispatcher
{
    public function dispatch(RequestInterface $request, ResponseInterface $response)
    {
        $str = 'extbase ' . str_replace('\\', '_', $request->getControllerObjectName());
        if ($request instanceof Request) {
            $str .= '->' . $request->getControllerActionName();
        }
        $stop = TimingUtility::stopWatch($str);
        parent::dispatch($request, $response);
        $stop();
    }
}
