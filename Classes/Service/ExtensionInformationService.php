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

namespace PSB\PsbFoundation\Service;

use Doctrine\DBAL\Exception\TableNotFoundException;
use InvalidArgumentException;
use PSB\PsbFoundation\Data\ExtensionInformationInterface;
use PSB\PsbFoundation\Exceptions\ImplementationException;
use PSB\PsbFoundation\Utility\StringUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ExtensionInformationService
 *
 * @package PSB\PsbFoundation\Service
 */
class ExtensionInformationService
{
    private const EXTENSION_INFORMATION_MAPPING_TABLE = 'tx_psbfoundation_extension_information_mapping';

    /**
     * @param ExtensionInformationInterface $extensionInformation
     * @param string                        $path
     *
     * @return mixed
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function getConfiguration(
        ExtensionInformationInterface $extensionInformation,
        string $path = ''
    ) {
        $path = str_replace('.', '/', $path);
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get($extensionInformation->getExtensionKey(), $path);

        if (is_array($extensionConfiguration)) {
            return GeneralUtility::makeInstance(TypoScriptService::class)
                ->convertTypoScriptArrayToPlainArray($extensionConfiguration);
        }

        return $extensionConfiguration;
    }

    /**
     * @return ExtensionInformationInterface[]
     */
    public function getExtensionInformation(): array
    {
        $extensionInformation = $this->getRegisteredClassInformation();
        $extensionInformationInstances = [];

        foreach ($extensionInformation as $information) {
            if (!ExtensionManagementUtility::isLoaded($information['extension_key'])) {
                $this->deregister($information['extension_key']);
                continue;
            }

            /** @var ExtensionInformationInterface $extensionInformationClass */
            $extensionInformationClass = GeneralUtility::makeInstance($information['class_name']);
            $extensionInformationInstances[$extensionInformationClass->getExtensionKey()] = $extensionInformationClass;
        }

        return $extensionInformationInstances;
    }

    /**
     * @param string $extensionKey
     *
     * @return string
     */
    public function getLanguageFilePath(string $extensionKey): string
    {
        return $this->getResourcePath($extensionKey) . 'Private/Language/';
    }

    /**
     * @return array
     */
    public function getRegisteredClassInformation(): array
    {
        try {
            return GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable(self::EXTENSION_INFORMATION_MAPPING_TABLE)
                ->select(['class_name', 'extension_key'], self::EXTENSION_INFORMATION_MAPPING_TABLE)
                ->fetchAll();
        } catch (TableNotFoundException $tableNotFoundException) {
            return [];
        }
    }

    /**
     * @param string $extensionKey
     *
     * @return string
     */
    public function getResourcePath(string $extensionKey): string
    {
        $subDirectoryPath = '/' . $extensionKey . '/Resources/';
        $resourcePath = Environment::getExtensionsPath() . $subDirectoryPath;

        if (is_dir($resourcePath)) {
            return $resourcePath;
        }

        return Environment::getFrameworkBasePath() . $subDirectoryPath;
    }

    /**
     * @param string $className
     *
     * @return string The controller name (without the 'Controller'-part at the end) or respectively the name of the
     *                related domain model
     */
    public function convertControllerClassToBaseName(string $className): string
    {
        $classNameParts = GeneralUtility::trimExplode('\\', $className, true);

        if (4 > count($classNameParts)) {
            throw new InvalidArgumentException(__CLASS__ . ': ' . $className . ' is not a full qualified (namespaced) class name!',
                1560233275);
        }

        $controllerNameParts = array_slice($classNameParts, 3);
        $fullControllerName = implode('\\', $controllerNameParts);

        if (!StringUtility::endsWith($fullControllerName, 'Controller')) {
            throw new InvalidArgumentException(__CLASS__ . ': ' . $className . ' is not a controller class!',
                1560233166);
        }

        return substr($fullControllerName, 0, -10);
    }

    /**
     * @param string $extensionKey
     */
    public function deregister(string $extensionKey): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::EXTENSION_INFORMATION_MAPPING_TABLE)
            ->delete(self::EXTENSION_INFORMATION_MAPPING_TABLE, ['extension_key' => $extensionKey]);
    }

    /**
     * @param string $className
     *
     * @return array
     */
    public function extractExtensionInformationFromClassName(string $className): array
    {
        $classNameParts = GeneralUtility::trimExplode('\\', $className, true);

        if (2 > count($classNameParts)) {
            throw new InvalidArgumentException(__CLASS__ . ': ' . $className . ' is not a full qualified (namespaced) class name!',
                1547120513);
        }

        return [
            'extensionKey'  => GeneralUtility::camelCaseToLowerCaseUnderscored($classNameParts[1]),
            'extensionName' => $classNameParts[1],
            'vendorName'    => $classNameParts[0],
        ];
    }

    /**
     * @param string $fileName
     *
     * @return string|null
     */
    public function extractVendorNameFromFile(string $fileName): ?string
    {
        $vendorName = null;

        if (file_exists($fileName)) {
            $file = fopen($fileName, 'rb');

            while ($line = fgets($file)) {
                if (StringUtility::beginsWith($line, 'namespace ')) {
                    $namespace = rtrim(GeneralUtility::trimExplode(' ', $line)[1], ';');
                    $vendorName = explode('\\', $namespace)[0];
                    break;
                }
            }
        }

        return $vendorName;
    }

    /**
     * @param string $className Full qualified class name of your extension information class (must implement
     *                          \PSB\PsbFoundation\Data\ExtensionInformationInterface, you can extend
     *                          \PSB\PsbFoundation\Data\AbstractExtensionInformation)
     * @param string $extensionKey
     *
     * @throws ImplementationException
     */
    public function register(
        string $className,
        string $extensionKey
    ): void {
        $this->validateExtensionInformationClass($className);
        $this->validateExtensionKey($extensionKey);

        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::EXTENSION_INFORMATION_MAPPING_TABLE)
            ->insert(self::EXTENSION_INFORMATION_MAPPING_TABLE,
                [
                    'class_name'    => $className,
                    'extension_key' => $extensionKey,
                ]
            );
    }

    /**
     * @param string $className
     *
     * @throws ImplementationException
     */
    private function validateExtensionInformationClass(string $className): void
    {
        if (!in_array(ExtensionInformationInterface::class, class_implements($className), true)) {
            throw new ImplementationException(__CLASS__ . ': ' . $className . ' has to implement ExtensionInformationInterface!',
                1568738348);
        }
    }

    /**
     * @param string $extensionKey
     *
     * @throws ImplementationException
     */
    private function validateExtensionKey(string $extensionKey): void
    {
        if (!ExtensionManagementUtility::isLoaded($extensionKey)) {
            throw new ImplementationException(__CLASS__ . ': The key "' . $extensionKey . '" does not match any installed extension!',
                1568738493);
        }
    }
}