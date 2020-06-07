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

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ImportExportFactory
{
    /**
     * @param string $format
     * @return ImportExportInterface
     */
    public static function getImportExportService(string $format)
    {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['lfeditor_impexp']['importExportClasses'][$format])
            || !class_exists(
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['lfeditor_impexp']['importExportClasses'][$format]
            )) {
            throw new \InvalidArgumentException("No ImportExport class definition found for $format", 1559922975);
        }

        /** @var ImportExportInterface $instance */
        $instance = GeneralUtility::makeInstance(
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['lfeditor_impexp']['importExportClasses'][$format]
        );

        return $instance;
    }
}
