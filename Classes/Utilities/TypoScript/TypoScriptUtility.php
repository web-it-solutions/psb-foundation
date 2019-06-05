<?php
declare(strict_types=1);

namespace PSB\PsbFoundation\Utilities;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 PSG Web Team <webdev@plan.de>, PSG Plan Service Gesellschaft mbH
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

use PSB\PsbFoundation\Data\ExtensionInformationInterface;
use PSB\PsbFoundation\Utilities\Backend\RegistrationUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * Class TypoScriptUtility
 * @package PSB\PsbFoundation\Utilities
 */
class TypoScriptUtility
{
    public const CONTENT_TYPES = [
        'HTML' => 'text/html',
        'XML'  => 'text/xml',
    ];

    public const INDENTATION = '    ';

    public const TYPO_SCRIPT_KEYS = [
        'CONDITION'   => '_condition',
        'IMPORT'      => '_import',
        'OBJECT_TYPE' => '_objectType',
    ];
    /**
     * @var string
     */
    private static $lineBreakAfterCurlyBracketClose = '';
    /**
     * @var string
     */
    private static $lineBreakBeforeCurlyBracketOpen = '';
    /**
     * @var string
     */
    private static $objectPath = '';

    /**
     * @param array $array
     *
     * @return string
     */
    public static function convertArrayToTypoScript(array $array): string
    {
        if (GeneralUtility::getApplicationContext()->isDevelopment()) {
            $backtrace = debug_backtrace();
            $debugInformation = [
                'class'    => $backtrace[1]['class'],
                'function' => $backtrace[1]['function'],
                'line'     => $backtrace[0]['line'],
            ];
            $debugOutput = '// TypoScript generated by '.$debugInformation['class'].':'.$debugInformation['function'].' in line '.$debugInformation['line'].LF;
        }

        $generatedTypoScript = self::buildTypoScriptFromArray($array);

        // reset formatting helpers
        self::resetLineBreaks();
        self::resetObjectPath();

        return ($debugOutput ?? '').$generatedTypoScript;
    }

    /**
     * @param PageObjectConfiguration $pageTypeConfiguration
     *
     * @return string
     */
    public static function registerNewPageObject(PageObjectConfiguration $pageTypeConfiguration): string
    {
        $typoScript = [
            self::TYPO_SCRIPT_KEYS['CONDITION']               => 'globalVar = TSFE:type = '.$pageTypeConfiguration->getTypeNum(),
            $pageTypeConfiguration->getTypoScriptObjectName() => [
                self::TYPO_SCRIPT_KEYS['OBJECT_TYPE'] => 'PAGE',
                'config'                              => [
                    'additionalHeaders'    => [
                        10 => [
                            'header' => 'Content-type: '.$pageTypeConfiguration->getContentType(),
                        ],
                    ],
                    'debug'                => 0,
                    'disableAllHeaderCode' => 1,
                    'sys_language_mode'    => 'ignore',
                ],
                'typeNum'                             => $pageTypeConfiguration->getTypeNum(),
                10                                    => [
                    self::TYPO_SCRIPT_KEYS['OBJECT_TYPE'] => 'USER_INT',
                    'action'                              => $pageTypeConfiguration->getAction(),
                    'controller'                          => $pageTypeConfiguration->getController(),
                    'extensionName'                       => $pageTypeConfiguration->getExtensionName(),
                    'pluginName'                          => $pageTypeConfiguration->getPluginName(),
                    'settings'                            => $pageTypeConfiguration->getSettings(),
                    'switchableControllerActions'         => [
                        $pageTypeConfiguration->getController() => [
                            1 => $pageTypeConfiguration->getAction(),
                        ],
                    ],
                    'userFunc'                            => 'TYPO3\CMS\Extbase\Core\Bootstrap->run',
                    'vendorName'                          => $pageTypeConfiguration->getVendorName(),
                ],
            ],
        ];

        return self::convertArrayToTypoScript($typoScript);
    }

    /**
     * @param string $extensionInformation
     * @param string $path
     * @param string $title
     */
    public static function registerTypoScript(
        string $extensionInformation,
        string $path = 'Configuration/TypoScript',
        string $title = 'Main configuration'
    ): void {
        if (RegistrationUtility::validateExtensionInformation($extensionInformation)) {
            /** @var ExtensionInformationInterface $extensionInformation */
            ExtensionManagementUtility::addStaticFile($extensionInformation::getExtensionKey(), $path, $title);
        }
    }

    /**
     * @param array $array
     * @param int   $indentationLevel
     *
     * @return string
     */
    private static function buildTypoScriptFromArray(array $array, int $indentationLevel = 0): string
    {
        ksort($array);
        $typoScript = '';

        if (isset($array[self::TYPO_SCRIPT_KEYS['CONDITION']])) {
            if (0 < $indentationLevel) {
                throw new UnexpectedValueException(__CLASS__.': TypoScript conditions must not be placed inside nested elements!',
                    1552992577);
            }

            $typoScript .= '['.$array[self::TYPO_SCRIPT_KEYS['CONDITION']].']'.LF;
            unset ($array[self::TYPO_SCRIPT_KEYS['CONDITION']]);
            $typoScript .= self::buildTypoScriptFromArray($array, $indentationLevel);
            $typoScript .= '[GLOBAL]'.LF;
        } else {
            foreach ($array as $key => $value) {
                $indentation = '' === self::$objectPath ? self::createIndentation($indentationLevel) : '';

                if (is_array($value)) {
                    if (isset($value[self::TYPO_SCRIPT_KEYS['OBJECT_TYPE']])) {
                        $typoScript .= (self::$lineBreakAfterCurlyBracketClose ? : self::$lineBreakBeforeCurlyBracketOpen).$indentation.$key.' = '.$value[self::TYPO_SCRIPT_KEYS['OBJECT_TYPE']].LF;
                        unset($value[self::TYPO_SCRIPT_KEYS['OBJECT_TYPE']]);
                        $typoScript .= self::processRemainingArray($indentationLevel, $key, $value);
                    } elseif (isset($value[self::TYPO_SCRIPT_KEYS['IMPORT']])) {
                        $typoScript .= (self::$lineBreakAfterCurlyBracketClose ? : self::$lineBreakBeforeCurlyBracketOpen).$indentation.$key.' < '.$value[self::TYPO_SCRIPT_KEYS['IMPORT']].LF;
                        unset($value[self::TYPO_SCRIPT_KEYS['IMPORT']]);
                        $typoScript .= self::processRemainingArray($indentationLevel, $key, $value);
                    } elseif (1 === count($value)) {
                        self::resetLineBreaks();
                        self::$objectPath .= $key.'.';
                        $typoScript .= $indentation.$key.'.'.self::buildTypoScriptFromArray($value,
                                $indentationLevel);
                    } else {
                        $typoScript .= self::$lineBreakBeforeCurlyBracketOpen.$indentation.$key.' {'.LF;
                        self::resetLineBreaks();
                        self::resetObjectPath();
                        $typoScript .= self::buildTypoScriptFromArray($value, $indentationLevel + 1);
                        $typoScript .= $indentation.'}'.LF;
                        self::$lineBreakAfterCurlyBracketClose = LF;
                    }
                } else {
                    self::resetObjectPath();
                    $typoScript .= self::$lineBreakAfterCurlyBracketClose.$indentation.$key.' = '.$value.LF;
                    self::$lineBreakAfterCurlyBracketClose = '';
                    self::$lineBreakBeforeCurlyBracketOpen = LF;
                }
            }
        }

        return $typoScript;
    }

    /**
     * @param int $indentationLevel
     *
     * @return string
     */
    private static function createIndentation(int $indentationLevel): string
    {
        $indentation = '';

        for ($i = 0; $i < $indentationLevel; $i++) {
            $indentation .= self::INDENTATION;
        }

        return $indentation;
    }

    /**
     * @param int        $indentationLevel
     * @param int|string $key
     * @param array      $value
     *
     * @return string
     */
    private static function processRemainingArray(int $indentationLevel, $key, array $value): string
    {
        $typoScript = '';

        if (!empty($value)) {
            self::resetLineBreaks();
            $typoScript .= self::createIndentation($indentationLevel).self::$objectPath.$key.' {'.LF;
            self::resetObjectPath();
            $typoScript .= self::buildTypoScriptFromArray($value, $indentationLevel + 1);
            $typoScript .= self::createIndentation($indentationLevel).'}'.LF;
            self::$lineBreakAfterCurlyBracketClose = LF;
        }

        return $typoScript;
    }

    private static function resetLineBreaks(): void
    {
        self::$lineBreakAfterCurlyBracketClose = '';
        self::$lineBreakBeforeCurlyBracketOpen = '';
    }

    private static function resetObjectPath(): void
    {
        self::$objectPath = '';
    }
}
