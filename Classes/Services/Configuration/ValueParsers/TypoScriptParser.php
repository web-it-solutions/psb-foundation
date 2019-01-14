<?php

namespace PS\PsFoundation\Services\Configuration\ValueParsers;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Daniel Ablass <dn@phantasie-schmiede.de>, Phantasie-Schmiede
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

use PS\PsFoundation\Services\TypoScriptProviderService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class TypoScriptParser
 * @package PS\PsFoundation\Services\Configuration\ValueParsers
 */
class TypoScriptParser implements ValueParserInterface
{
    public const MARKER_TYPE = 'PS:TS';

    /**
     * @param string|null $value
     *
     * @return mixed
     * @throws \Exception
     */
    public function processValue(?string $value)
    {
        $typoscript = TypoScriptProviderService::getTypoScriptConfiguration();

        if (null === $typoscript) {
            return null;
        }

        $typoScriptPath = GeneralUtility::trimExplode('.', $value, true);

        try {
            $result = ArrayUtility::getValueByPath($typoscript, $typoScriptPath, '.');
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(self::class.': FlexForm marker '.self::MARKER_TYPE.' must be followed by a valid TypoScript path!',
                1547210715);
        }

        return $result;
    }
}