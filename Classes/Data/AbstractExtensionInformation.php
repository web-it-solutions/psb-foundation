<?php
declare(strict_types=1);

namespace PSB\PsbFoundation\Data;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractExtensionInformation
 * @package PSB\PsbFoundation\Data
 */
abstract class AbstractExtensionInformation implements ExtensionInformationInterface
{
    /**
     * may be overridden in extending class
     */
    public const MODULES = [
        //        'submoduleKey' => [\Your\Module\Controller::class, \Your\Module\AnotherController::class],
    ];

    /**
     * may be overridden in extending class
     */
    public const PLUGINS = [
        //        'pluginName' => [\Your\Plugin\Controller::class, \Your\Plugin\AnotherController::class],
    ];

    /**
     * @var string
     */
    protected $extensionKey;

    /**
     * @var string
     */
    protected $extensionName;

    /**
     * @var string
     */
    protected $vendorName;

    public function __construct()
    {
        [$this->vendorName, $this->extensionName] = explode('\\', get_class($this));
        $this->extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($this->extensionName);
    }

    /**
     * @return string
     */
    public function getExtensionKey(): string
    {
        return $this->extensionKey;
    }

    /**
     * @return string
     */
    public function getExtensionName(): string
    {
        return $this->extensionName;
    }

    /**
     * @return array
     */
    public function getModules(): array
    {
        return static::MODULES;
    }

    /**
     * @return array
     */
    public function getPlugins(): array
    {
        return static::PLUGINS;
    }

    /**
     * @return string
     */
    public function getVendorName(): string
    {
        return $this->vendorName;
    }
}