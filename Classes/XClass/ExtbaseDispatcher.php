<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\XClass;

use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Request as WebRequest;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

if (version_compare(TYPO3_branch, '11.0', '>=')) {
    class ExtbaseDispatcher extends Dispatcher
    {
        public function dispatch(RequestInterface $request): ResponseInterface
        {
            $str = 'extbase ' . str_replace('\\', '_', $request->getControllerObjectName());
            if ($request instanceof Request) {
                $str .= '->' . $request->getControllerActionName();
            }
            $stop = TimingUtility::stopWatch($str);
            $response = parent::dispatch($request);
            $stop();
            return $response;
        }
    }
} else {
    class ExtbaseDispatcher extends Dispatcher
    {
        public function dispatch(RequestInterface $request, ResponseInterface $response)
        {
            $str = 'extbase ' . str_replace('\\', '_', $request->getControllerObjectName());
            if ($request instanceof WebRequest) {
                $str .= '->' . $request->getControllerActionName();
            }
            $stop = TimingUtility::stopWatch($str);
            parent::dispatch($request, $response);
            $stop();
        }
    }
}
