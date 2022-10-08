<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Kanti: server-timing',
    'description' => '',
    'category' => 'module',
    'author' => 'Matthias Vogel',
    'author_email' => 'git@kanti.de',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => \Kanti\ServerTiming\Utility\VersionUtility::getVersion(),
    'constraints' => [
        'depends' => [
            'typo3' => '9.0.0-11.999.999',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
