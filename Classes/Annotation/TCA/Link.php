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

namespace PSB\PsbFoundation\Annotation\TCA;

use PSB\PsbFoundation\Service\Configuration\Fields;

/**
 * Class Link
 *
 * @Annotation
 * @package PSB\PsbFoundation\Annotation\TCA
 */
class Link extends Input
{
    public const TYPE = Fields::FIELD_TYPES['LINK'];

    /**
     * @var string|null
     */
    protected ?string $renderType = 'inputLink';
}