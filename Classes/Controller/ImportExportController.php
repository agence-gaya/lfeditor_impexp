<?php

namespace GAYA\LfeditorImpexp\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) GAYA Manufacture digitale (https://www.gaya.fr)
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use SGalinski\Lfeditor\Controller\AbstractBackendController;
use SGalinski\Lfeditor\Exceptions\LFException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\DiffUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use GAYA\LfeditorImpexp\Service\ImportExportFactory;

/**
 * EditFile controller. It contains extbase actions of EditFile page.
 */
class ImportExportController extends AbstractBackendController
{
    /**
     * @param ViewInterface $view
     */
    protected function initializeView(ViewInterface $view)
    {
        $view->assign('editingMode', $this->session->getDataByKey('editingMode'));
        $view->assign('editingModeOptions', $this->configurationService->getAvailableEditingModes());
        $view->assign('canChangeEditingModes', $this->session->getDataByKey('canChangeEditingModes'));
    }

    /**
     * Displays the list of language files for all extensions
     *
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function indexAction()
    {
        try {
            $this->prepareExtensionAndLangFileOptions();
        } catch (LFException $e) {
            $this->addLFEFlashMessage($e);
        }
    }

    /**
     * Export the language file
     *
     * @param string $extensionSelection
     * @param string $languageFileSelection
     * @return string
     * @throws LFException
     */
    public function exportAction(string $extensionSelection, string $languageFileSelection)
    {
        $lfeditorConfig = $this->configurationService->getExtConfig();

        // load file data
        $this->configurationService->initFileObject(
            $languageFileSelection,
            $extensionSelection
        );
        $langData = $this->configurationService->getFileObj()->getLocalLangData();

        // load language options
        $defaultLanguage = $lfeditorConfig['defaultLanguage'];
        $languageKeys = array_keys($this->configurationService->menuLangList($langData, '', $this->backendUser));

        // prepare export
        $fileExport = ImportExportFactory::getImportExportService('csv');
        $fileExport->setLangData($langData);
        $fileExport->setLanguageKeys($languageKeys, $defaultLanguage);
        $filename = $this->getFilenameFromLanguageFilePath($extensionSelection.'/'.$languageFileSelection);
        $fileExport->export($filename);

        // stop the Extbase rendering
        return '';
    }

    /**
     * @param string $extensionSelection
     * @param string $languageFileSelection
     * @param array $file
     * @param string $operation
     * @throws LFException
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function importAction(string $extensionSelection, string $languageFileSelection, array $file = null, string $operation = null)
    {
        $this->view->assignMultiple(
            [
                'extensionSelection' => $extensionSelection,
                'languageFileSelection' => $languageFileSelection,
            ]
        );

        if ($file !== null && $operation !== null) {
            if (!is_uploaded_file($file['tmp_name'])
                || $file['error'] !== \UPLOAD_ERR_OK
                || !in_array($file['type'], ['text/csv', 'application/vnd.ms-excel'])
                || !GeneralUtility::verifyFilenameAgainstDenyPattern($file['name'])
            ) {
                $this->addFlashMessage(
                    'An error occured with the uploaded file (upload error, wrong type, etc.)',
                    'Upload error',
                    AbstractMessage::ERROR
                );

                return;
            }

            $lfeditorConfig = $this->configurationService->getExtConfig();

            // load original data
            $this->configurationService->initFileObject(
                $languageFileSelection,
                $extensionSelection
            );
            $langData = $this->configurationService->getFileObj()->getLocalLangData();

            // load language options
            $defaultLanguage = $lfeditorConfig['defaultLanguage'];
            $languageKeys = array_keys($this->configurationService->menuLangList($langData, '', $this->backendUser));

            // load import file
            $fileExport = ImportExportFactory::getImportExportService('csv');
            $fileExport->setLangData($langData);
            $fileExport->setLanguageKeys($languageKeys, $defaultLanguage);

            try {
                $newLangData = $fileExport->readFile($file['tmp_name']);
            } catch (\GAYA\LfeditorImpexp\Exception $e) {
                $this->addFlashMessage(
                    $e->getMessage(),
                    'Upload error',
                    AbstractMessage::ERROR
                );

                return;
            }

            if ($operation === 'preview') {
                // preview changes
                $previewLangData = $this->prepareDiff($langData, $newLangData);
                $this->view->assign('previewLangData', $previewLangData);

                return;
            }

            // write changes
            try {
                $this->configurationService->execWrite($newLangData, array(), false, $languageKeys);
            } catch (LFException $e) {
                $this->addFlashMessage(
                    $e->getMessage(),
                    'Import error',
                    AbstractMessage::ERROR
                );

                return;
            }

            // confirm and redirect
            $this->addFlashMessage(
                'Language file has been imported',
                'Success',
                AbstractMessage::OK
            );

            $this->redirect('index');
        }
    }

    /**
     * @param string $editingMode
     */
    public function setEditingModeAction(string $editingMode)
    {
        if ($this->session->getDataByKey('canChangeEditingModes')) {
            $this->session->setDataByKey('editingMode', $editingMode);
            $this->redirect('index');
        }
    }

    /**
     * Renders HTML table-rows with the comparison information of an sys_history entry record
     *
     * @param array $sourceLangData
     * @param array $newLangData
     * @return array
     */
    protected function prepareDiff(array $sourceLangData, array $newLangData): array
    {
        $diffData = [];

        /* @var DiffUtility $diffUtility */
        $diffUtility = GeneralUtility::makeInstance(DiffUtility::class);
        $diffUtility->stripTags = false;

        foreach ($newLangData as $lang => $labels) {
            foreach ($labels as $constant => $newLabel) {
                $originalLabel = $sourceLangData[$lang][$constant] ?? '';

                if ($originalLabel === '' && $newLabel === '') {
                    continue;
                }

                // Create diff-result:
                $diffres = $diffUtility->makeDiffDisplay($originalLabel, $newLabel);
                $diffData[$lang][$constant] = str_replace(['\r\n', '\n'], PHP_EOL, $diffres);
            }
        }

        return $diffData;
    }

    /**
     * Prepares language file select options for each extension and sets combined data in view.
     *
     * @return void
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     * @throws LFException
     */
    protected function prepareExtensionAndLangFileOptions()
    {
        /** @var CacheManager $cacheManager */
        $cacheManager = $this->objectManager->get(CacheManager::class);
        $extensions = $cacheManager->getCache('lfeditor_impexp')->get('extensions');
        if (empty($extensions)) {
            $extensions = [];
            $extensionOptions = $this->configurationService->menuExtList();
            foreach ($extensionOptions as $extAddress => $extLabel) {
                $extension['extLabel'] = $extLabel;
                $extension['languageFileOptions'] = [];
                $isExtensionGroupStart = $extAddress === '###extensionGroup###'.$extLabel;
                $extension['isExtensionGroupStart'] = $isExtensionGroupStart;
                try {
                    if (!$isExtensionGroupStart) {
                        $extension['languageFileOptions'] = $this->configurationService->menuLangFileList($extAddress);
                        if (empty($extension['languageFileOptions'])) {
                            continue;
                        }
                    }
                } catch (LFException $e) {
                    continue;
                }
                $extensions[$extAddress] = $extension;
            }
            $cacheManager->getCache('lfeditor_impexp')->set('extensions', $extensions);
        }

        $this->view->assign('extensions', $extensions);
    }

    /**
     * Build the name of the exported file from the absolute path
     *
     * @param string $languageFilePath
     * @return mixed
     */
    protected function getFilenameFromLanguageFilePath(string $languageFilePath)
    {
        $extRelPath = \SGalinski\Lfeditor\Utility\Typo3Lib::transTypo3File($languageFilePath, false);
        $filename = str_replace('EXT:', '', $extRelPath);
        $filename = str_replace('/', '_', $filename);

        return $filename;
    }

}
