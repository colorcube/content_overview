<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Web>Info, Content Overview',
    'description' => 'Backend module which gives and overview of the the content elements in a page tree.',
    'category' => 'module',
    'author' => 'René Fritz',
    'author_email' => 'r.fritz@colorcube.de',
    'author_company' => 'Colorcube',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '0.1.1',
    'constraints' => array(
        'depends' => array(
            'typo3' => '7.4.0-7.99.99',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
    'autoload' => array(
        'psr-4' => array(
            'Colorcube\\ContentOverview\\' => 'Classes'
        ),
    ),
);
