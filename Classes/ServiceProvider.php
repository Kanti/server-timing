<?php

declare(strict_types=1);

namespace Kanti\ServerTiming;

use Closure;
use Kanti\ServerTiming\Middleware\XClassMiddlewareDispatcher;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Backend\Http\Application as ApplicationBE;
use TYPO3\CMS\Backend\Http\RequestHandler as RequestHandlerBe;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\AbstractServiceProvider;
use TYPO3\CMS\Frontend\Http\Application as ApplicationFE;
use TYPO3\CMS\Frontend\Http\RequestHandler as RequestHandlerFE;

/**
 * @deprecated can be removed if TYPO3 14 is lowest supported version
 */
final class ServiceProvider extends AbstractServiceProvider
{
    protected static function getPackagePath(): string
    {
        return __DIR__ . '/../';
    }

    public static function getPackageName(): string
    {
        return 'kanti/server-timing';
    }

    /**
     * @return array<string, Closure>
     */
    public function getFactories(): array
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            return [];
        }
        return [
            ApplicationFE::class => self::getApplicationFE(...),
            ApplicationBE::class => self::getApplicationBE(...),
        ];
    }

    public static function getApplicationFE(ContainerInterface $container): ApplicationFE
    {
        $requestHandler = new XClassMiddlewareDispatcher(
            $container->get(RequestHandlerFE::class),
            $container->get('frontend.middlewares'),
            $container,
        );
        return new ApplicationFE(
            $requestHandler,
            $container->get(Context::class),
        );
    }

    public static function getApplicationBE(ContainerInterface $container): ApplicationBE
    {
        $requestHandler = new XClassMiddlewareDispatcher(
            $container->get(RequestHandlerBe::class),
            $container->get('backend.middlewares'),
            $container,
        );
        return new ApplicationBE(
            $requestHandler,
            $container->get(Context::class),
        );
    }
}
