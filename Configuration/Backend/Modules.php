<?php

declare(strict_types=1);

use GAYA\LfeditorImpexp\Controller\ImportExportController;

return [
    'user_lfeditorImpexp' => [
        'parent' => 'system',
        'position' => [
            'after' => 'system_lfeditor',
        ],
        'access' => 'user',
        'labels' => 'LLL:EXT:lfeditor_impexp/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extension-lfeditor_impexp-ext-icon',
        'extensionName' => 'LfeditorImpexp',
        'controllerActions' => [
            ImportExportController::class => [
                'index',
                'import',
                'export',
                'setEditingMode',
            ],
        ],
    ],
];
