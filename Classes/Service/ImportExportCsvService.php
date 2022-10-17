<?php

namespace GAYA\LfeditorImpexp\Service;

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
use TYPO3\CMS\Core\Type\File\FileInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImportExportCsvService implements ImportExportInterface
{
    protected $langData;
    protected $languageKeys = [];
    protected $defaultLanguageKey = '';
    protected $defaultLanguageData = [];
    protected $csvDelimiter = ';';

    /**
     * @param array $langData
     */
    public function setLangData(array $langData)
    {
        $this->langData = $langData;
        $this->defaultLanguageData = [];
    }

    /**
     * @param array $languageKeys
     * @param string $defaultLanguageKey
     */
    public function setLanguageKeys(array $languageKeys, string $defaultLanguageKey)
    {
        if (!isset($this->langData)) {
            throw new \RuntimeException("langData must be set before setting languageKeys", 1559925897);
        }

        $this->languageKeys = $languageKeys;
        $this->defaultLanguageKey = $defaultLanguageKey;
        $this->defaultLanguageData = $this->langData[$this->defaultLanguageKey] ?? [];

        if (($i = array_search($this->defaultLanguageKey, $this->languageKeys)) !== false) {
            unset($this->languageKeys[$i]);
        }
        sort($this->languageKeys);
    }

    /**
     * @param string $filename
     */
    public function export(string $filename)
    {
        $tempFilePath = GeneralUtility::tempnam('lfeditor_impexp_', '.csv');
        $f = fopen($tempFilePath, 'w');

        $headers = [
            'constant',
            'default',
        ];
        $headers = array_merge($headers, $this->languageKeys);
        fputcsv($f, $headers, $this->csvDelimiter);

        foreach ($this->defaultLanguageData as $constant => $value) {
            $row = [
                $constant,
                $value,
            ];
            foreach ($this->languageKeys as $languageKey) {
                $row[] = $this->langData[$languageKey][$constant] ?? '';
            }
            fputcsv($f, $row, $this->csvDelimiter);
        }

        fclose($f);
        $this->sendFileToBrowser($tempFilePath, $filename.'.csv');
        GeneralUtility::unlink_tempfile($tempFilePath);
    }

    /**
     * @param string $filePath
     * @return array
     * @throws Exception
     */
    public function readFile(string $filePath)
    {
        $h = fopen($filePath, 'r');
        if ($h === false) {
            throw new Exception('An error occured when reading the uploaded file');
        }

        $headers = [];
        $langfileEditNewLangData = [];
        $i = 0;
        while (($data = fgetcsv($h, 0, $this->csvDelimiter)) !== false) {
            if (++$i === 1) {
                $headers = $data;
                continue;
            }

            $constant = $data[0];
            for ($key = 1, $n = count($data); $key < $n; $key++) {
                $langKey = $headers[$key];
                $langfileEditNewLangData[$langKey][$constant] = $data[$key];
            }
        }
        fclose($h);

        return $langfileEditNewLangData;
    }

    /**
     * Send file to client browser
     *
     * @param string $filePath
     * @param string $fileName
     */
    protected function sendFileToBrowser(string $filePath, string $fileName)
    {
        $fileInfo = new FileInfo($filePath);
        $mimeType = $fileInfo->getMimeType();

        switch ($mimeType) {
            case 'application/zip':
                //android want it uppercase
                $fileName = basename($fileName, '.zip').'.ZIP';
                break;
        }

        // http://perishablepress.com/http-headers-file-downloads/
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, no-cache, post-check=0, pre-check=0');
        header('Content-Type: '.$mimeType);
        header('Content-Disposition: attachment; filename="'.$fileName.'"');
        header('Content-Length: '.$fileInfo->getSize());

        ob_end_clean();
        @readfile($filePath);
    }
}
