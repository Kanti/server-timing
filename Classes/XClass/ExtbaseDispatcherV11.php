<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\XClass;

use Kanti\ServerTiming\Utility\TimingUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

use function class_alias;

if (version_compare((new Typo3Version())->getBranch(), '11.0', '<')) {
    class_alias(Dispatcher::class, ExtbaseDispatcherV11::class);
    return;
}

class ExtbaseDispatcherV11 extends Dispatcher
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
