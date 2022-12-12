<?php

use Kanti\ServerTiming\Middleware\AdminpanelSqlLoggingMiddleware;
use Kanti\ServerTiming\Utility\GuzzleUtility;
use Kanti\ServerTiming\XClass\ExtbaseDispatcherLegacy;
use Kanti\ServerTiming\XClass\CoreRequestFactory;
use Kanti\ServerTiming\XClass\ExtbaseDispatcherV11;
use TYPO3\CMS\Adminpanel\Middleware\SqlLogging;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Mvc\Dispatcher;

if (version_compare((new Typo3Version())->getBranch(), '11.0', '>=')) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Dispatcher::class] = [
        'className' => ExtbaseDispatcherV11::class,
    ];
} else {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Dispatcher::class] = [
        'className' => ExtbaseDispatcherLegacy::class,
    ];
}
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][SqlLogging::class] = [
    'className' => AdminpanelSqlLoggingMiddleware::class,
];

$GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['server_timing'] = GuzzleUtility::getHandler();
