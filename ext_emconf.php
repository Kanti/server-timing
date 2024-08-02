<?php

use Composer\InstalledVersions;

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Kanti: server-timing',
    'description' => 'Show timings of Database and HTTP Calls (send them to Sentry)',
    'category' => 'module',
    'author' => 'Matthias Vogel',
    'author_email' => 'git@kanti.de',
    'state' => 'stable',
    'version' => InstalledVersions::getPrettyVersion('kanti/server-timing'),
    'constraints' => [
        'depends' => [
            'typo3' => '11.0.0-13.999.999',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
