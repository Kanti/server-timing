<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\XClass;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * only used for TYPO3 v9
 * makes the option $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] availalble in v9
 * is a default option in >=v10
 */
class CoreRequestFactory extends RequestFactory
{
    protected function getClient(): ClientInterface
    {
        $httpOptions = $GLOBALS['TYPO3_CONF_VARS']['HTTP'];
        $httpOptions['verify'] = filter_var($httpOptions['verify'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $httpOptions['verify'];

        if (isset($GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler']) && is_array($GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'])) {
            $stack = HandlerStack::create();
            foreach ($GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] ?? [] as $name => $handler) {
                $stack->push($handler, (string)$name);
            }
            $httpOptions['handler'] = $stack;
        }

        return GeneralUtility::makeInstance(Client::class, $httpOptions);
    }
}
