<?php

use Kanti\ServerTiming\Middleware\AdminpanelSqlLoggingMiddleware;
use Kanti\ServerTiming\SqlLogging\LoggingMiddleware;
use Kanti\ServerTiming\Utility\GuzzleUtility;
use TYPO3\CMS\Adminpanel\Middleware\SqlLogging;
use TYPO3\CMS\Core\Information\Typo3Version;

// can be used instead after TYPO3 support is set to >=12
//if (version_compare((new Typo3Version())->getBranch(), '12.3', '>=')) {
//    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverMiddlewares']['server_timing_logging'] = LoggingMiddleware::class;
//} else {
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][SqlLogging::class] = [
    'className' => AdminpanelSqlLoggingMiddleware::class,
];
//}

$handler = GuzzleUtility::getHandler();
if ($handler) {
    $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']['server_timing'] = $handler;
}
