<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Web>Info, Content Overview',
    'description' => 'Backend module which gives and overview of the the content elements in a page tree.',
    'category' => 'module',
    'author' => 'René Fritz',
    'author_email' => 'r.fritz@colorcube.de',
    'author_company' => 'Colorcube',
    'state' => 'stable',
    'version' => '0.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '7.6.0-8.99.99',
        ]
    ],
    'autoload' => [
        'psr-4' => [
            'Colorcube\\ContentOverview\\' => 'Classes'
        ]
    ]
];
