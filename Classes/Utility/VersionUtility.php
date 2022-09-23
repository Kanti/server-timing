<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\Utility;

use PackageVersions\Versions;
use Throwable;

final class VersionUtility
{
    private function __construct()
    {
    }

    public static function getVersion(): string
    {
        $str = 'dev';
        try {
            return explode('@', Versions::getVersion('kanti/server-timing'))[0] ?? $str;
        } catch (Throwable $e) {
            return $str;
        }
    }
}
