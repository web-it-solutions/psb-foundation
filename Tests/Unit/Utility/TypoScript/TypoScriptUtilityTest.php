<?php
declare(strict_types=1);

/*
 * This file is part of PSB Foundation.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace PSB\PsbFoundation\Utility\TypoScript;

use Generator;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class TypoScriptUtilityTest
 *
 * @package PSB\PsbFoundation\Utility\TypoScript
 */
class TypoScriptUtilityTest extends UnitTestCase
{
    /**
     * @return Generator
     */
    public static function convertArrayToTypoScriptDataProvider(): Generator
    {
        yield 'empty array' => [
            [],
            '',
        ];
        yield 'register typeNum' => [
            [
                TypoScriptUtility::TYPO_SCRIPT_KEYS['COMMENT']   => 'Comment on top level',
                TypoScriptUtility::TYPO_SCRIPT_KEYS['CONDITION'] => 'request.getQueryParams()[\'type\'] == ' . 1589385441,
                'ajax_psb_foundation_typoscriptutility_test'     => [
                    TypoScriptUtility::TYPO_SCRIPT_KEYS['COMMENT']     => 'Comment on second level',
                    TypoScriptUtility::TYPO_SCRIPT_KEYS['OBJECT_TYPE'] => 'PAGE',
                    10                                                 => [
                        '_objectType'                 => 'USER_INT',
                        'action'                      => 'test',
                        'controller'                  => 'TypoScriptUtility',
                        'extensionName'               => 'PsbFoundation',
                        'pluginName'                  => 'TypoScriptUtilityTest',
                        'switchableControllerActions' => [
                            'TypoScriptUtility' => [
                                1 => 'test',
                            ],
                        ],
                        'userFunc'                    => 'TYPO3\CMS\Extbase\Core\Bootstrap->run',
                        'vendorName'                  => 'PSB',
                    ],
                    'config'                                           => [
                        TypoScriptUtility::TYPO_SCRIPT_KEYS['COMMENT'] => 'Comment on third level',
                        'additionalHeaders'                            => [
                            10 => [
                                TypoScriptUtility::TYPO_SCRIPT_KEYS['COMMENT'] => 'Comment on fourth level',
                                'header'                                       => 'Content-type: text/html',
                            ],
                        ],
                        'admPanel'                                     => [
                            TypoScriptUtility::TYPO_SCRIPT_KEYS['COMMENT'] => 'Comment inside fourth level',
                            true,
                        ],
                        'debug'                                        => true,
                        'disableAllHeaderCode'                         => true,
                    ],
                    'typeNum'                                          => 1589385441,
                ],
            ],
            file_get_contents(__DIR__ . '/TypoScriptExample.typoscript'),
        ];
    }

    /**
     * @test
     * @dataProvider convertArrayToTypoScriptDataProvider
     *
     * @param array  $array
     * @param string $expectedResult
     *
     * @return void
     */
    public function convertArrayToTypoScript(array $array, string $expectedResult): void
    {
        if (true === Environment::getContext()
                ->isTesting()) {
            $expectedResult = '// TypoScript generated by PSB\PsbFoundation\Utility\TypoScript\TypoScriptUtilityTest:convertArrayToTypoScript in line 94' . LF . $expectedResult;
        }

        self::assertEquals(
            $expectedResult,
            TypoScriptUtility::convertArrayToTypoScript($array)
        );
    }
}
