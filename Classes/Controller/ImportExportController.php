<?php

declare(strict_types=1);

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
use GAYA\LfeditorImpexp\Exception;
use GAYA\LfeditorImpexp\Service\ImportExportFactory;
use Override;
use Psr\Http\Message\ResponseInterface;
use SGalinski\Lfeditor\Controller\AbstractBackendController;
use SGalinski\Lfeditor\Exceptions\LFException;
use SGalinski\Lfeditor\Utility\Typo3Lib;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\DiffUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EditFile controller. It contains extbase actions of EditFile page.
 */
class ImportExportController extends AbstractBackendController
{
    #[Override]
    protected function commonViewRenderingActionSettings()
    {
        parent::commonViewRenderingActionSettings();
        $this->moduleTemplate->assign('editingMode', $this->session->getDataByKey('editingMode'));
        $this->moduleTemplate->assign('editingModeOptions', $this->configurationService->getAvailableEditingModes());
        $this->moduleTemplate->assign('canChangeEditingModes', $this->session->getDataByKey('canChangeEditingModes'));
    }

    /**
     * Displays the list of language files for all extensions.
     *
     * @throws NoSuchCacheException
     */
    public function indexAction(): ResponseInterface
    {
        try {
            $this->prepareExtensionAndLangFileOptions();
        } catch (LFException $lfException) {
            $this->addLFEFlashMessage($lfException);
        }

        return $this->moduleTemplate->renderResponse('ImportExport/Index');
    }

    /**
     * Export the language file.
     *
     * @throws LFException
     *
     * @return string
     */
    public function exportAction(string $extensionSelection, string $languageFileSelection): ResponseInterface
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

        $filename = $this->getFilenameFromLanguageFilePath($extensionSelection . '/' . $languageFileSelection);
        $fileExport->export($filename);

        return $this->htmlResponse('');
    }

    /**
     * @param array<UploadedFile>|null $files
     *
     * @throws LFException
     * @throws NoSuchCacheException
     */
    public function importAction(string $extensionSelection, string $languageFileSelection, ?array $files = null, ?string $operation = null): ResponseInterface
    {
        $this->moduleTemplate->assignMultiple(
            [
                'extensionSelection' => $extensionSelection,
                'languageFileSelection' => $languageFileSelection,
            ]
        );

        if ($files === null || $files === [] || $operation === null) {
            return $this->moduleTemplate->renderResponse('ImportExport/Import');
        }

        /** @var FileNameValidator $fileNameValidator */
        $fileNameValidator = GeneralUtility::makeInstance(FileNameValidator::class);

        $file = $files[0];

        if (!is_uploaded_file($file->getTemporaryFileName())
            || $file->getError() !== \UPLOAD_ERR_OK
            || !in_array($file->getClientMediaType(), ['text/csv', 'application/vnd.ms-excel'])
            || !$fileNameValidator->isValid($file->getClientFilename())
        ) {
            $this->addFlashMessage(
                'An error occured with the uploaded file (upload error, wrong type, etc.)',
                'Upload error',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->moduleTemplate->renderResponse('ImportExport/Import');
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
            $newLangData = $fileExport->readFile($file->getTemporaryFileName());
        } catch (Exception $exception) {
            $this->addFlashMessage(
                $exception->getMessage(),
                'Upload error',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->moduleTemplate->renderResponse('ImportExport/Import');
        }

        if ($operation === 'preview') {
            // preview changes
            $previewLangData = $this->prepareDiff($langData, $newLangData);
            $this->moduleTemplate->assign('previewLangData', $previewLangData);

            return $this->moduleTemplate->renderResponse('ImportExport/Import');
        }

        // write changes
        try {
            $this->configurationService->execWrite($newLangData, [], false, $languageKeys);
        } catch (LFException $lfException) {
            $this->addFlashMessage(
                $lfException->getMessage(),
                'Import error',
                ContextualFeedbackSeverity::ERROR
            );

            return $this->moduleTemplate->renderResponse('ImportExport/Import');
        }

        // confirm and redirect
        $this->addFlashMessage(
            'Language file has been imported',
            'Success'
        );

        return $this->redirect('index');
    }

    public function setEditingModeAction(string $editingMode): ResponseInterface
    {
        if ($this->session->getDataByKey('canChangeEditingModes')) {
            $this->session->setDataByKey('editingMode', $editingMode);
        }

        return $this->redirect('index');
    }

    /**
     * Renders HTML table-rows with the comparison information of a sys_history entry record.
     */
    protected function prepareDiff(array $sourceLangData, array $newLangData): array
    {
        $diffData = [];

        /* @var DiffUtility $diffUtility */
        $diffUtility = GeneralUtility::makeInstance(DiffUtility::class);

        foreach ($newLangData as $lang => $labels) {
            foreach ($labels as $constant => $newLabel) {
                $originalLabel = $sourceLangData[$lang][$constant] ?? '';

                if ($originalLabel === '' && $newLabel === '') {
                    continue;
                }

                // Create diff-result:
                $diffRes = $diffUtility->diff($originalLabel, $newLabel);
                $diffData[$lang][$constant] = str_replace(['\r\n', '\n'], PHP_EOL, $diffRes);
            }
        }

        return $diffData;
    }

    /**
     * Prepares language file select options for each extension and sets combined data in view.
     *
     * @throws NoSuchCacheException
     * @throws LFException
     */
    #[Override]
    protected function prepareExtensionAndLangFileOptions(): void
    {
        /** @var CacheManager $cacheManager */
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $extensions = $cacheManager->getCache('lfeditor_impexp')->get('extensions');
        if (empty($extensions)) {
            $extensions = [];
            $extensionOptions = $this->configurationService->menuExtList();
            foreach ($extensionOptions as $extAddress => $extLabel) {
                $extension['extLabel'] = $extLabel;
                $extension['languageFileOptions'] = [];
                $isExtensionGroupStart = $extAddress === '###extensionGroup###' . $extLabel;
                $extension['isExtensionGroupStart'] = $isExtensionGroupStart;
                try {
                    if (!$isExtensionGroupStart) {
                        $extension['languageFileOptions'] = $this->configurationService->menuLangFileList($extAddress);
                        if (empty($extension['languageFileOptions'])) {
                            continue;
                        }
                    }
                } catch (LFException) {
                    continue;
                }

                $extensions[$extAddress] = $extension;
            }

            $cacheManager->getCache('lfeditor_impexp')->set('extensions', $extensions);
        }

        $this->moduleTemplate->assign('extensions', $extensions);
    }

    /**
     * Build the name of the exported file from the absolute path.
     *
     * @throws \Exception
     */
    protected function getFilenameFromLanguageFilePath(string $languageFilePath): string
    {
        $extRelPath = Typo3Lib::transTypo3File($languageFilePath, false);
        $filename = str_replace('EXT:', '', $extRelPath);

        return str_replace('/', '_', $filename);
    }

}
