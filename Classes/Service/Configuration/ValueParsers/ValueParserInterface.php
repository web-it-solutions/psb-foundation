<?php
declare(strict_types=1);

namespace PSB\PsbFoundation\Service\Configuration\ValueParsers;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Daniel Ablass <dn@phantasie-schmiede.de>, PSbits
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

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Interface ValueParserInterface
 *
 * Your parser class also has to define a constant named MARKER_TYPE (the part between the beginning "###" and ":").
 * Example: const MARKER_TYPE = 'EXAMPLE';
 * Usage: ###EXAMPLE:value###
 *
 * @package PSB\PsbFoundation\Service\Configuration\ValueParsers
 */
interface ValueParserInterface extends SingletonInterface
{
    /**
     * @param string|null $value the string between ':' and '###'
     *
     * @return mixed
     */
    public function processValue(?string $value);
}