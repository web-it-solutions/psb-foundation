<?php

namespace PS\PsFoundation\Services\DocComment\ValueParsers;

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

/**
 * Class TcaFieldConfigParser
 * @package PS\PsFoundation\Services\DocComment\ValueParsers
 */
class TcaFieldConfigParser extends AbstractValuePairsParser
{
    public const ANNOTATION_TYPE = 'PS\PsFoundation\Tca\FieldConfig';

    /**
     * @param string|null $valuePairs
     *
     * @return mixed
     * @throws \Exception
     */
    public function processValue(?string $valuePairs)
    {
        $result = parent::processValue($valuePairs);

        // transform associative array to simple array for TCA
        if ('select' === $result['type'] && isset ($result['items']) && is_array($result['items'])) {
            $result['items'] = array_map(function ($key, $value) {
                return [$key, $value];
            }, array_keys($result['items']), array_values($result['items']));
        }

        return $result;
    }
}