<?php

$EM_CONF['lfeditor_impexp'] = [
    'title' => 'Language File Editor - Import/Export',
    'description' => 'Add import/export functionality for LFEditor',
    'category' => 'module',
    'version' => '2.0.0',
    'state' => 'stable',
    'author' => 'Rémy DANIEL',
    'author_email' => 'contact@gaya.fr',
    'author_company' => 'GAYA',
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '10.4.0-10.4.99',
                    'lfeditor' => '6.0.0-7.1.99',
                ],
            'conflicts' =>
                [],
            'suggests' =>
                [],
        ],
];
