<?php
declare(strict_types=1);

namespace PSB\PsbFoundation\ViewHelpers\Variable;

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

use InvalidArgumentException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Class ArrayMergeViewHelper
 * @package PSB\PsbFoundation\ViewHelpers\Variable
 */
class ArrayMergeViewHelper extends AbstractViewHelper
{
    /**
     * @throws Exception
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('arrays', 'array', 'arrays to be merged', true);
        $this->registerArgument('as', 'string', 'variable name of the merged result', true);
        $this->registerArgument('overwrite', 'boolean', 'overwrites the variable if already existing', false, false);
    }

    public function render(): void
    {
        $templateVariableContainer = $this->renderingContext->getVariableProvider();

        if (!$this->arguments['overwrite'] && $templateVariableContainer->exists($this->arguments['as'])) {
            throw new InvalidArgumentException(
                __CLASS__.': Variable "'.$this->arguments['as'].'" already exists!',
                1549520834
            );
        }
        array_walk($this->arguments['arrays'], function (&$value) {
            if (!is_array($value)) {
                $value = [];
            }
        });
        $templateVariableContainer->add($this->arguments['as'], array_merge(...$this->arguments['arrays']));
    }
}