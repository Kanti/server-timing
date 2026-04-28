<?php

/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Kanti: server-timing',
    'description' => 'Show timings of Database and HTTP Calls (send them to Sentry)',
    'category' => 'module',
    'author' => 'Matthias Vogel',
    'author_email' => 'git@kanti.de',
    'state' => 'stable',
    'version' => 'dev-',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
