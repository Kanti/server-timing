<?php

use Composer\InstalledVersions;

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
    'version' => InstalledVersions::getPrettyVersion('kanti/server-timing'),
    'constraints' => [
        'depends' => [
            'typo3' => '11.0.0-12.999.999',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
