<?php

use Composer\InstalledVersions;

/** @var string $_EXTKEY */
try {
    $version = InstalledVersions::getPrettyVersion('kanti/server-timing');
} catch (Exception $e) {
    $version = '99.99.99'; // allow install in typo3-main
}
$EM_CONF[$_EXTKEY] = [
    'title' => 'Kanti: server-timing',
    'description' => 'Show timings of Database and HTTP Calls (send them to Sentry)',
    'category' => 'module',
    'author' => 'Matthias Vogel',
    'author_email' => 'git@kanti.de',
    'state' => 'stable',
    'version' => $version,
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
