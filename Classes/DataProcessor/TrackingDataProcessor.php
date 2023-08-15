<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\DataProcessor;

use Kanti\ServerTiming\Dto\StopWatch;
use Kanti\ServerTiming\Utility\TimingUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

final class TrackingDataProcessor implements DataProcessorInterface, SingletonInterface
{
    /** @var array<array-key, StopWatch>  */
    private array $stopWatches = [];

    /**
     * @param array<array-key, mixed> $contentObjectConfiguration
     * @param array<array-key, mixed> $processorConfiguration
     * @param array<array-key, mixed> $processedData
     * @return array<array-key, mixed>
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData)
    {
        $id = (string)$processorConfiguration['id'];
        $stopWatch = $this->stopWatches[$id] ?? null;
        $stopWatch?->stopIfNot();

        if ($processorConfiguration['type'] === 'start') {
            $this->stopWatches[$id] = TimingUtility::stopWatch('dataP', $processorConfiguration['for'] . ' ' . $processorConfiguration['key'] . '.' . $processorConfiguration['processorOrAlias']);
        } else {
            unset($this->stopWatches[$id]);
        }

        return $processedData;
    }
}
