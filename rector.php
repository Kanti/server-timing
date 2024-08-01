<?php

declare(strict_types=1);

use PLUS\GrumPHPConfig\RectorSettings;
use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./var/cache/rector');

    $rectorConfig->paths(
        array_filter(explode("\n", (string)shell_exec("git ls-files | xargs ls -d 2>/dev/null | grep -E '\.(php)$'")))
    );

    // define sets of rules
    $rectorConfig->sets(
        [
            ...RectorSettings::sets(true),
            ...RectorSettings::setsTypo3(false),
        ]
    );

    // remove some rules
    // ignore some files
    $rectorConfig->skip(
        [
            ...RectorSettings::skip(),
            ...RectorSettings::skipTypo3(),

            /**
             * rector should not touch these files
             */
            RemoveUnusedPublicMethodParameterRector::class,
            MakeInheritedMethodVisibilitySameAsParentRector::class => [
                __DIR__ . '/Classes/ServiceProvider.php',
            ],
            RecastingRemovalRector::class => [
                __DIR__ . '/Classes/SqlLogging/LoggingConnection.php',
            ],
            ParamTypeByMethodCallTypeRector::class => [
                __DIR__ . '/Classes/SqlLogging/SqlLoggerCore11.php',
            ],
            //__DIR__ . '/src/Example',
            //__DIR__ . '/src/Example.php',
        ]
    );
};
