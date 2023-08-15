<?php

declare(strict_types=1);

namespace Kanti\ServerTiming\DataProcessor;

use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentDataProcessor;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

final class XClassContentDataProcessor extends ContentDataProcessor
{
    /**
     * @param array<array-key, mixed> $configuration
     * @param array<array-key, mixed> $variables
     * @return array<array-key, mixed>
     */
    public function process(ContentObjectRenderer $cObject, array $configuration, array $variables)
    {
        $processors = $configuration['dataProcessing.'] ?? [];
        if (!$processors) {
            return $variables;
        }

        $processorKeys = ArrayUtility::filterAndSortByNumericKeys($processors);
        $index = 1;
        $newDataProcessing = [];
        foreach ($processorKeys as $key) {
            $processorClassOrAlias = $processors[$key];

            $uniqId = uniqId();

            $newDataProcessing[$index] = TrackingDataProcessor::class;
            $newDataProcessing[$index . '.'] = ['key' => $key, 'processorOrAlias' => $processorClassOrAlias, 'type' => 'start', 'id' => $uniqId, 'for' => $cObject->getCurrentTable() . ':' . $cObject->data['uid']];
            $index++;
            $newDataProcessing[$index] = $processorClassOrAlias;
            $newDataProcessing[$index . '.'] = $processors[$key . '.'] ?? [];
            $index++;
            $newDataProcessing[$index] = TrackingDataProcessor::class;
            $newDataProcessing[$index . '.'] = ['type' => 'stop', 'id' => $uniqId];
            $index++;
        }

        $configuration['dataProcessing.'] = $newDataProcessing;

        return parent::process($cObject, $configuration, $variables);
    }
}
