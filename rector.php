<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use PLUS\GrumPHPConfig\RectorSettings;
use Rector\Config\RectorConfig;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\PHPUnit\Set\PHPUnitSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory('./var/cache/rector');

    $rectorConfig->paths(
        array_filter(explode("\n", (string)shell_exec("git ls-files | xargs ls -d 2>/dev/null | grep -E '\.(php|html|typoscript)$'")))
    );

    // define sets of rules
    $rectorConfig->sets(
        [
            ...RectorSettings::sets(true),
            ...RectorSettings::setsTypo3(false),
            PHPUnitLevelSetList::UP_TO_PHPUNIT_100,
            PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
            PHPUnitSetList::PHPUNIT_CODE_QUALITY,
            PHPUnitSetList::PHPUNIT_YIELD_DATA_PROVIDER,
            PHPUnitSetList::PHPUNIT_EXCEPTION,
            PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD,
            PHPUnitSetList::REMOVE_MOCKS,
        ]
    );

    // remove some rules
    // ignore some files
    $rectorConfig->skip(
        [
            ...RectorSettings::skip(),
            ...RectorSettings::skipTypo3(),

            MakeInheritedMethodVisibilitySameAsParentRector::class

            /**
             * rector should not touch these files
             */
            //__DIR__ . '/src/Example',
            //__DIR__ . '/src/Example.php',
        ]
    );
};
