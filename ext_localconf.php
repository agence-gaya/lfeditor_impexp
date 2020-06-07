<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['lfeditor_impexp'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['lfeditor_impexp'] = array();
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['lfeditor_impexp']['importExportClasses'] = [
    'csv' => \GAYA\LfeditorImpexp\Service\ImportExportCsvService::class
];
