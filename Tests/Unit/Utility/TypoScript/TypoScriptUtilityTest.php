<?php
declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace PSB\PsbFoundation\Utility\TypoScript;

use Generator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Class TypoScriptUtilityTest
 *
 * @package PSB\PsbFoundation\Utility\TypoScript
 */
class TypoScriptUtilityTest extends UnitTestCase
{
    /**
     * @test
     * @dataProvider convertArrayToTypoScriptDataProvider
     *
     * @param array  $array
     * @param string $expectedResult
     */
    public function convertArrayToTypoScript(array $array, string $expectedResult): void
    {
        self::assertEquals(
            $expectedResult,
            TypoScriptUtility::convertArrayToTypoScript($array)
        );
    }

    /**
     * @return Generator
     */
    public function convertArrayToTypoScriptDataProvider(): Generator
    {
        yield 'empty array' => [
            [],
            '',
        ];
    }
}