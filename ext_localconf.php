<?php

use Kanti\ServerTiming\DataProcessor\XClassContentDataProcessor;
use Kanti\ServerTiming\Extbase\XClassExtbaseDispatcher;
use Kanti\ServerTiming\SqlLogging\LoggingMiddleware;
use Kanti\ServerTiming\Utility\GuzzleUtility;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;

$GLOBALS['TYPO3_CONF_VARS']['DB']['globalDriverMiddlewares']['global-driver-middleware-identifier'] = [
    'target' => LoggingMiddleware::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Dispatcher::class] = [
    'className' => XClassExtbaseDispatcher::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][ContentDataProcessor::class] = [
    'className' => XClassContentDataProcessor::class,
];

$handler = GuzzleUtility::getHandler();
if ($handler) {
    $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['server_timing'] = $handler;
}
