<?php
declare(strict_types=1);

namespace PS\PsFoundation\Utilities;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Daniel Ablass <dn@phantasie-schmiede.de>, Phantasie-Schmiede
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use PS\PsFoundation\Services\DocComment\DocCommentParserService;
use PS\PsFoundation\Services\DocComment\ValueParsers\TcaMappingParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class VariableUtility
 * @package PS\PsFoundation\Utilities
 */
class VariableUtility
{
    /**
     * Although calculated on a base of 2, the average user might be confused when he is shown the technically correct
     * unit names like KiB, MiB or GiB. Hence the inaccurate, "old" units are being used.
     */
    public const FILE_SIZE_UNITS = [
        'B'  => 0,
        'KB' => 1,
        'MB' => 2,
        'GB' => 3,
        'TB' => 4,
        'PB' => 5,
        'EB' => 6,
        'ZB' => 7,
        'YB' => 8,
    ];

    /**
     * @param string $className
     *
     * @return string
     */
    public static function convertClassNameToExtensionKey(string $className): string
    {
        $classNameParts = GeneralUtility::trimExplode('\\', $className, true);

        if (isset($classNameParts[1])) {
            return GeneralUtility::camelCaseToLowerCaseUnderscored($classNameParts[1]);
        }

        throw new \InvalidArgumentException(self::class.': '.$className.' is not a full qualified (namespaced) class name!',
            1547120513);
    }

    /**
     * @param string $className
     *
     * @return string
     * @throws \ReflectionException
     */
    public static function convertClassNameToTableName(string $className): string
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $docCommentParserService = $objectManager->get(DocCommentParserService::class);
        $docComment = $docCommentParserService->parsePhpDocComment($className);

        if (isset($docComment[TcaMappingParser::ANNOTATION_TYPE]['table'])) {
            return $docComment[TcaMappingParser::ANNOTATION_TYPE]['table'];
        }

        $classNameParts = GeneralUtility::trimExplode('\\', $className, true);
        $classNameParts[0] = 'tx';

        return strtolower(implode('_', $classNameParts));
    }

    /**
     * Convert file size to a human readable string
     *
     * To enforce a specific unit use a value of FILE_SIZE_UNITS as second parameter
     *
     * @param int      $bytes
     * @param int|null $unit
     * @param int      $decimals
     * @param string   $decimalSeparator
     * @param string   $thousandsSeparator
     *
     * @return string
     */
    public static function convertFileSize(
        int $bytes,
        int $unit = null,
        int $decimals = 2,
        $decimalSeparator = ',',
        $thousandsSeparator = '.'
    ): string {
        if ($unit) {
            $bytes /= (1024 ** $unit);
        } else {
            $power = 0;

            while ($bytes >= 1024) {
                $bytes /= 1024;
                $power++;
            }
        }

        $unitString = array_search($power ?? $unit, self::FILE_SIZE_UNITS, true);

        return number_format($bytes, $decimals, $decimalSeparator, $thousandsSeparator).' '.$unitString;
    }

    /**
     * @param string      $propertyName
     * @param string|null $className
     *
     * @return string
     * @throws \ReflectionException
     */
    public static function convertPropertyNameToColumnName(string $propertyName, string $className = null): string
    {
        if (null !== $className) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $docCommentParserService = $objectManager->get(DocCommentParserService::class);
            $docComment = $docCommentParserService->parsePhpDocComment($className, $propertyName);
        }

        return $docComment[TcaMappingParser::ANNOTATION_TYPE]['column'] ?? GeneralUtility::camelCaseToLowerCaseUnderscored($propertyName);
    }

    /**
     * @param string|null $variable
     * @param bool        $convertEmptyStringToNull
     *
     * @return bool|float|int|string|null
     */
    public static function convertString(?string $variable, $convertEmptyStringToNull = false)
    {
        if ($convertEmptyStringToNull && '' === $variable) {
            return null;
        }

        if (is_numeric($variable)) {
            if (false === strpos($variable, '.')) {
                return (int)$variable;
            }

            return (double)$variable;
        }

        if (0 < strpos($variable, '::')) {
            return constant($variable);
        }

        switch ($variable) {
            case 'true':
                return true;
                break;
            case 'false':
                return false;
                break;
            default:
                return $variable;
        }
    }
}