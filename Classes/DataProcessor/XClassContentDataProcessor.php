<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\DataProcessor;

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

if ((new Typo3Version())->getMajorVersion() <= 13) {
    final class XClassContentDataProcessor extends ContentDataProcessor
    {
        use XClassContentDataProcessorTrait;
    }
} else {
    final readonly class XClassContentDataProcessor extends ContentDataProcessor
    {
        use XClassContentDataProcessorTrait;
    }
}
