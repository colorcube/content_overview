<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Web>Info, Content Overview',
    'description' => 'Backend module which gives and overview of the the content elements in a page tree.',
    'category' => 'module',
    'author' => 'René Fritz',
    'author_email' => 'r.fritz@colorcube.de',
    'author_company' => 'Colorcube',
    'state' => 'stable',
    'version' => '0.3.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
        ]
    ],
    'autoload' => [
        'psr-4' => [
            'Colorcube\\ContentOverview\\' => 'Classes'
        ]
    ]
];
