<?php

namespace PS\PsFoundation\Views;

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

use PS\PsFoundation\Services\GlobalVariableService;

/**
 * Class TemplateView
 * @package PS\PsFoundation\Views
 */
class TemplateView extends \TYPO3\CMS\Fluid\View\TemplateView
{

    /**
     * Init view
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function initializeView(): void
    {
        parent::initializeView();
        $this->assignMultiple(GlobalVariableService::getGlobalVariables());
    }

    /**
     * Renders a partial.
     *
     * @param string $partialName
     * @param string $sectionName
     * @param array $variables
     * @param boolean $ignoreUnknown Ignore an unknown section and just return an empty string
     *
     * @return string
     * @throws \TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException
     */
    public function renderPartial($partialName, $sectionName, array $variables, $ignoreUnknown = false): string
    {
        $globalVariables = GlobalVariableService::getGlobalVariables();
        foreach ($globalVariables as $key => $value) {
            if (!isset($variables[$key])) {
                $variables[$key] = $value;
            }
        }

        return parent::renderPartial($partialName, $sectionName, $variables, $ignoreUnknown);
    }

}