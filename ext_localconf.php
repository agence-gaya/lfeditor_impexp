<?php

use GAYA\LfeditorImpexp\Service\ImportExportCsvService;

if (!defined('TYPO3')) {
	die('Access denied.');
}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['lfeditor_impexp'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['lfeditor_impexp'] = [];
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['lfeditor_impexp']['importExportClasses'] = [
    'csv' => ImportExportCsvService::class
];
