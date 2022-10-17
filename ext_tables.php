<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied!!!');
}

if (TYPO3_MODE === 'BE') {

    $lfeditorExtConf = \SGalinski\Lfeditor\Utility\ExtensionUtility::getExtensionConfiguration();
    TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'LfeditorImpexp',
        $lfeditorExtConf['beMainModuleName'] ?? 'user',
        'LFEditorImpexp',
        'after:LFEditor',
        array(
            \GAYA\LfeditorImpexp\Controller\ImportExportController::class => 'index,import,export,setEditingMode',
        ),
        array(
            'access' => 'user,group',
            'icon' => 'EXT:lfeditor_impexp/Resources/Public/Icons/Extension.svg',
            'labels' => 'LLL:EXT:lfeditor_impexp/Resources/Private/Language/locallang_mod.xlf',
        )
    );

}
