<?php

$EM_CONF['lfeditor_impexp'] = [
    'title' => 'Language File Editor - Import/Export',
    'description' => 'Add import/export functionality for LFEditor',
    'category' => 'module',
    'shy' => 0,
    'version' => '1.0.1',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'loadOrder' => '',
    'module' => '',
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Rémy DANIEL',
    'author_email' => 'contact@gaya.fr',
    'author_company' => 'GAYA',
    'CGLcompliance' => null,
    'CGLcompliance_note' => null,
    'constraints' =>
        [
            'depends' =>
                [
                    'typo3' => '8.7.20-9.5.99',
                    'lfeditor' => '5.0.0-5.1.99',
                ],
            'conflicts' =>
                [],
            'suggests' =>
                [],
        ],
];
