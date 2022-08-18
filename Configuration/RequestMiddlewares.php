<?php

return [
    'frontend' => [
        'server-timing/first' => [
            'target' => \Kanti\ServerTiming\Middleware\FirstMiddleware::class,
            'before' => [
                'staticfilecache/fallback',
                'typo3/cms-frontend/timetracker',
            ],
        ],
        'server-timing/last' => [
            'target' => \Kanti\ServerTiming\Middleware\LastMiddleware::class,
            'after' => [
                'solr/service/pageexporter',
                'typo3/cms-frontend/output-compression',
                'fluidtypo3/vhs/asset-inclusion',
                'apache-solr-for-typo3/page-indexer-finisher',
                'solr/service/pageexporter',
                'typo3/cms-adminpanel/data-persister',
            ],
        ],
    ],
    'backend' => [
        'server-timing/first' => [
            'target' => \Kanti\ServerTiming\Middleware\FirstMiddleware::class,
            'before' => [
                'typo3/cms-core/normalized-params-attribute',
                'typo3/cms-backend/locked-backend',
            ],
        ],
        'server-timing/last' => [
            'target' => \Kanti\ServerTiming\Middleware\LastMiddleware::class,
            'after' => [
                'typo3/cms-frontend/output-compression',
                'typo3/cms-backend/response-headers',
                'typo3/cms-backend/site-resolver',
                'typo3/cms-backend/legacy-document-template',
                'typo3/cms-extbase/signal-slot-deprecator',
            ],
        ],
    ],
];
