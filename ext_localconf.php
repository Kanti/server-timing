<?php

use Kanti\ServerTiming\Middleware\AdminpanelSqlLoggingMiddleware;
use Kanti\ServerTiming\Utility\GuzzleUtility;
use Kanti\ServerTiming\XClass\ExtbaseDispatcher;
use Kanti\ServerTiming\XClass\CoreRequestFactory;
use TYPO3\CMS\Adminpanel\Middleware\SqlLogging;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Dispatcher::class] = [
    'className' => ExtbaseDispatcher::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][SqlLogging::class] = [
    'className' => AdminpanelSqlLoggingMiddleware::class,
];

if (version_compare(TYPO3_branch, '10.0', '<')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][RequestFactory::class] = [
        'className' => CoreRequestFactory::class,
    ];
}
$GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['server_timing'] = GuzzleUtility::getHandler();
