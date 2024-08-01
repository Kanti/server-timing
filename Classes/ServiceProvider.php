<?php

declare(strict_types=1);

namespace Kanti\ServerTiming;

use Closure;
use Kanti\ServerTiming\Middleware\XClassMiddlewareDispatcher;
use Psr\Container\ContainerInterface;
use TYPO3\CMS\Backend\Http\Application as ApplicationBE;
use TYPO3\CMS\Backend\Http\RequestHandler as RequestHandlerBe;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Package\AbstractServiceProvider;
use TYPO3\CMS\Core\Routing\BackendEntryPointResolver;
use TYPO3\CMS\Frontend\Http\Application as ApplicationFE;
use TYPO3\CMS\Frontend\Http\RequestHandler as RequestHandlerFE;

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
        if (version_compare((new Typo3Version())->getBranch(), '13.0', '>=')) {
            return new ApplicationFE(
                $requestHandler,
                $container->get(Context::class),
            );
        }

        if (version_compare((new Typo3Version())->getBranch(), '12.0', '>=') && class_exists(BackendEntryPointResolver::class)) {
            return new ApplicationFE(
                $requestHandler,
                $container->get(ConfigurationManager::class),
                $container->get(Context::class),
                $container->get(BackendEntryPointResolver::class),
            );
        }

        return new ApplicationFE(
            $requestHandler,
            $container->get(ConfigurationManager::class),
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
        if (version_compare((new Typo3Version())->getBranch(), '13.0', '>=')) {
            return new ApplicationBE(
                $requestHandler,
                $container->get(Context::class),
            );
        }

        if (version_compare((new Typo3Version())->getBranch(), '12.0', '>=') && class_exists(BackendEntryPointResolver::class)) {
            return new ApplicationBE(
                $requestHandler,
                $container->get(ConfigurationManager::class),
                $container->get(Context::class),
                $container->get(BackendEntryPointResolver::class),
            );
        }

        return new ApplicationBE(
            $requestHandler,
            $container->get(ConfigurationManager::class),
            $container->get(Context::class),
        );
    }
}
