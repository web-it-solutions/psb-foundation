<?php
/** @noinspection UnsupportedStringOffsetOperationsInspection */
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

namespace PSB\PsbFoundation\Service\Configuration;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\IndexedReader;
use InvalidArgumentException;
use JsonException;
use PSB\PsbFoundation\Annotation\PageType;
use PSB\PsbFoundation\Annotation\ModuleAction;
use PSB\PsbFoundation\Annotation\ModuleConfig;
use PSB\PsbFoundation\Annotation\PluginAction;
use PSB\PsbFoundation\Annotation\PluginConfig;
use PSB\PsbFoundation\Controller\Backend\AbstractModuleController;
use PSB\PsbFoundation\Data\ExtensionInformationInterface;
use PSB\PsbFoundation\Service\ExtensionInformationService;
use PSB\PsbFoundation\Service\LocalizationService;
use PSB\PsbFoundation\Traits\PropertyInjection\FlexFormServiceTrait;
use PSB\PsbFoundation\Traits\PropertyInjection\IconRegistryTrait;
use PSB\PsbFoundation\Utility\StringUtility;
use PSB\PsbFoundation\Utility\TypoScript\PageObjectConfiguration;
use PSB\PsbFoundation\Utility\TypoScript\TypoScriptUtility;
use PSB\PsbFoundation\Utility\ValidationUtility;
use ReflectionClass;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ArrayUtility as Typo3CoreArrayUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

/**
 * Class RegistrationService
 *
 * @package PSB\PsbFoundation\Service\Configuration
 */
class RegistrationService
{
    use FlexFormServiceTrait, IconRegistryTrait;

    public const ICON_SUFFIXES = [
        'CONTENT_FROM_PID' => '-contentFromPid',
        'ROOT'             => '-root',
        'HIDE_IN_MENU'     => '-hideinmenu',
    ];

    public const PAGE_TYPE_REGISTRATION_MODES = [
        'EXT_TABLES'   => 'ext_tables',
        'TCA_OVERRIDE' => 'tca_override',
    ];

    private const COLLECT_MODES = [
        'CONFIGURE_PLUGINS' => 'configurePlugins',
        'REGISTER_MODULES'  => 'registerModules',
        'REGISTER_PLUGINS'  => 'registerPlugins',
    ];

    /**
     * This static variable is used to keep track of already registered wizard groups and is pre-filled with TYPO3's
     * default groups as defined in
     * typo3\sysext\backend\Configuration\TSConfig\Page\Mod\Wizards\NewContentElement.tsconfig
     *
     * @var string[]
     */
    private array $contentElementWizardGroups = [
        'common',
        'forms',
        'menu',
        'plugins',
        'special',
    ];

    /**
     * For use in ext_localconf.php files
     *
     * @param string      $extensionKey
     * @param string      $group
     * @param string      $pluginName
     * @param string|null $iconIdentifier
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    public function addPluginToElementWizard(
        string $extensionKey,
        string $group,
        string $pluginName,
        string $iconIdentifier = null
    ): void {
        $iconIdentifier = $iconIdentifier ?? $extensionKey . '-' . str_replace('_', '-',
                GeneralUtility::camelCaseToLowerCaseUnderscored($pluginName));
        $ll = 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/Backend/Configuration/TSConfig/Page/wizard.xlf:' . $group . '.elements.' . lcfirst($pluginName);
        $description = $ll . '.description';
        $title = $ll . '.title';
        $listType = str_replace('_', '', $extensionKey) . '_' . mb_strtolower($pluginName);
        $localizationService = GeneralUtility::makeInstance(LocalizationService::class);

        if (false === $localizationService->translationExists($description)) {
            $description = '';
        }

        if (false === $localizationService->translationExists($title)) {
            $title = $pluginName;
        }

        $configuration = [
            'description'          => $description,
            'iconIdentifier'       => $this->iconRegistry->isRegistered($iconIdentifier) ? $iconIdentifier : 'content-plugin',
            'title'                => $title,
            'tt_content_defValues' => [
                'CType'     => 'list',
                'list_type' => $listType,
            ],
        ];

        $this->addElementWizardItem($configuration, $extensionKey, $group, $listType);
    }

    /**
     * For use in ext_localconf.php files
     *
     * @param ExtensionInformationInterface $extensionInformation
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    public function configurePlugins(ExtensionInformationInterface $extensionInformation): void
    {
        if (!is_iterable($extensionInformation->getPlugins())) {
            return;
        }

        foreach ($extensionInformation->getPlugins() as $pluginName => $controllerCollection) {
            if (!is_iterable($controllerCollection)) {
                // @TODO: Add warning?
                continue;
            }

            [
                $pluginConfiguration,
                $controllersAndCachedActions,
                $controllersAndUncachedActions,
            ] = $this->collectActionsAndConfiguration($controllerCollection,
                self::COLLECT_MODES['CONFIGURE_PLUGINS'], $pluginName);

            ExtensionUtility::configurePlugin(
                $extensionInformation->getExtensionName(),
                $pluginName,
                $controllersAndCachedActions,
                $controllersAndUncachedActions
            );

            if (isset($pluginConfiguration[PluginConfig::class])) {
                /** @var PluginConfig $pluginConfig */
                $pluginConfig = $pluginConfiguration[PluginConfig::class];
                $group = $pluginConfig->getGroup();
                $iconIdentifier = $pluginConfig->getIconIdentifier();
            }

            $this->addPluginToElementWizard($extensionInformation->getExtensionKey(),
                $group ?? mb_strtolower($extensionInformation->getVendorName()),
                $pluginName,
                $iconIdentifier ?? null);
        }
    }

    /**
     * For use in ext_tables.php files
     *
     * @param ExtensionInformationInterface $extensionInformation
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    public function registerModules(ExtensionInformationInterface $extensionInformation): void
    {
        if (!is_iterable($extensionInformation->getModules())) {
            return;
        }

        $localizationService = GeneralUtility::makeInstance(LocalizationService::class);

        foreach ($extensionInformation->getModules() as $submoduleKey => $controllerClassNames) {
            if (is_iterable($controllerClassNames)) {
                [
                    $moduleConfiguration,
                    $controllersAndActions,
                ] = $this->collectActionsAndConfiguration($controllerClassNames,
                    self::COLLECT_MODES['REGISTER_MODULES']);

                if (isset($moduleConfiguration[ModuleConfig::class])) {
                    /** @var ModuleConfig $moduleConfig */
                    $moduleConfig = $moduleConfiguration[ModuleConfig::class];
                    $iconIdentifier = $moduleConfig->getIconIdentifier();
                    $mainModuleName = $moduleConfig->getMainModuleName();
                    $position = $moduleConfig->getPosition();
                    $access = $moduleConfig->getAccess();
                    $icon = $moduleConfig->getIcon();
                    $labels = $moduleConfig->getLabels();
                    $navigationComponentId = $moduleConfig->getNavigationComponentId();
                }

                $iconIdentifier = $iconIdentifier ?? 'module-' . $submoduleKey;

                if (!isset($labels)) {
                    $labels = 'LLL:EXT:' . $extensionInformation->getExtensionKey() . '/Resources/Private/Language/Backend/Modules/' . $submoduleKey . '.xlf';
                }

                $localizationService->translationExists($labels . ':mlang_labels_tabdescr');
                $localizationService->translationExists($labels . ':mlang_labels_tablabel');
                $localizationService->translationExists($labels . ':mlang_tabs_tab');

                ExtensionUtility::registerModule(
                    $extensionInformation->getExtensionName(),
                    $mainModuleName ?? 'web',
                    $submoduleKey,
                    $position ?? '',
                    $controllersAndActions,
                    [
                        'access'                => $access ?? 'group, user',
                        'icon'                  => $icon ?? null,
                        'iconIdentifier'        => $this->iconRegistry->isRegistered($iconIdentifier) ? $iconIdentifier : 'content-plugin',
                        'labels'                => $labels,
                        'navigationComponentId' => $navigationComponentId ?? null,
                    ]
                );
            }
        }
    }

    /**
     * @param ExtensionInformationInterface $extensionInformation
     * @param string                        $mode
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    public function registerPageTypes(ExtensionInformationInterface $extensionInformation, string $mode): void
    {
        ValidationUtility::checkValueAgainstConstant(self::PAGE_TYPE_REGISTRATION_MODES, $mode);
        $pageTypes = $extensionInformation->getPageTypes();

        if (empty($pageTypes)) {
            return;
        }

        foreach ($pageTypes as $doktype => $configuration) {
            if (self::PAGE_TYPE_REGISTRATION_MODES['EXT_TABLES'] === $mode) {
                $this->addPageTypeToGlobals($configuration, $doktype);
            } else {
                $this->addPageTypeToPagesTca($configuration, $doktype, $extensionInformation->getExtensionKey());
            }
        }
    }

    /**
     * For use in Configuration/TCA/Overrides/tt_content.php files
     *
     * @param ExtensionInformationInterface $extensionInformation
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    public function registerPlugins(ExtensionInformationInterface $extensionInformation): void
    {
        if (!is_iterable($extensionInformation->getPlugins())) {
            return;
        }

        $localizationService = GeneralUtility::makeInstance(LocalizationService::class);

        foreach ($extensionInformation->getPlugins() as $pluginName => $controllerCollection) {
            if (!is_iterable($controllerCollection)) {
                continue;
            }

            [$pluginConfiguration] = $this->collectActionsAndConfiguration($controllerCollection,
                self::COLLECT_MODES['REGISTER_PLUGINS']);

            $flexFormFilePath = 'EXT:' . $extensionInformation->getExtensionKey() . '/Configuration/FlexForms/' . $pluginName . '.xml';

            if (isset($pluginConfiguration[PluginConfig::class])) {
                /** @var PluginConfig $pluginConfig */
                $pluginConfig = $pluginConfiguration[PluginConfig::class];
                $title = $pluginConfig->getTitle();

                if ('' !== $pluginConfig->getFlexForm()) {
                    $flexFormFilePath = $pluginConfig->getFlexForm();

                    if (false === strpos($flexFormFilePath, 'EXT:')) {
                        $flexFormFilePath = 'EXT:' . $extensionInformation->getExtensionKey() . '/Configuration/FlexForms/' . $flexFormFilePath;
                    }
                }
            }

            $flexFormFilePath = GeneralUtility::getFileAbsFileName($flexFormFilePath);

            if (file_exists($flexFormFilePath)) {
                $pluginSignature = strtolower($extensionInformation->getExtensionName()) . '_' . strtolower($pluginName);
                $this->flexFormService->register(file_get_contents($flexFormFilePath), $pluginSignature);
            }

            if (!isset($title)) {
                $title = 'LLL:EXT:' . $extensionInformation->getExtensionKey() . '/Resources/Private/Language/Backend/Configuration/TCA/Overrides/tt_content.xlf:plugin.' . $pluginName . '.title';
                $localizationService->translationExists($title);
            }

            $iconIdentifier = $extensionInformation->getExtensionKey() . '-' . str_replace('_', '-',
                    GeneralUtility::camelCaseToLowerCaseUnderscored($pluginName));

            ExtensionUtility::registerPlugin(
                $extensionInformation->getExtensionName(),
                $pluginName,
                $title,
                $this->iconRegistry->isRegistered($iconIdentifier) ? $iconIdentifier : 'content-plugin'
            );

            unset ($title);
        }
    }

    /**
     * @param string $extensionKey
     * @param string $key
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    private function addElementWizardGroup(string $extensionKey, string $key): void
    {
        $header = 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/Backend/Configuration/TSConfig/Page/wizard.xlf:' . $key . '.header';
        GeneralUtility::makeInstance(LocalizationService::class)->translationExists($header);
        $pageTS['mod']['wizards']['newContentElement']['wizardItems'][$key] = [
            'header' => $header,
            'show'   => '*',
        ];

        ExtensionManagementUtility::addPageTSConfig(TypoScriptUtility::convertArrayToTypoScript($pageTS));
        $this->contentElementWizardGroups[] = $key;
    }

    /**
     * @param array  $configuration
     * @param string $extensionKey
     * @param string $group
     * @param string $key
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    private function addElementWizardItem(
        array $configuration,
        string $extensionKey,
        string $group,
        string $key
    ): void {
        if (!in_array($group, $this->contentElementWizardGroups, true)) {
            $this->addElementWizardGroup($extensionKey, $group);
        }

        $newPageTS['mod']['wizards']['newContentElement']['wizardItems'][$group]['elements'][$key] = $configuration;
        ExtensionManagementUtility::addPageTSConfig(TypoScriptUtility::convertArrayToTypoScript($newPageTS));
    }

    /**
     * @param array $configuration
     * @param int   $doktype
     *
     * @return void
     */
    private function addPageTypeToGlobals(array $configuration, int $doktype): void
    {
        // Add new page type:
        $GLOBALS['PAGES_TYPES'][$doktype] = [
            'type'          => $configuration['type'] ?? 'web',
            'allowedTables' => $configuration['allowedTables'] ? implode(',', $configuration['allowedTables']) : '*',
        ];

        // Allow backend users to drag and drop the new page type:
        ExtensionManagementUtility::addUserTSConfig(
            'options.pageTree.doktypesToShowInNewPageDragArea := addToList(' . $doktype . ')'
        );
    }

    /**
     * @param array  $configuration
     * @param int    $doktype
     * @param string $extensionKey
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws InvalidConfigurationTypeException
     * @throws JsonException
     */
    private function addPageTypeToPagesTca(
        array $configuration,
        int $doktype,
        string $extensionKey
    ): void {
        $table = 'pages';
        $label = $configuration['label'] ?? 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/Backend/Configuration/TCA/Overrides/pages.xlf:pageType.' . $configuration['name'];

        if (false === GeneralUtility::makeInstance(LocalizationService::class)->translationExists($label)) {
            $label = ucfirst(str_replace('_', ' ',
                GeneralUtility::camelCaseToLowerCaseUnderscored($configuration['name'])));
        }

        // Add new page type as possible select item:
        ExtensionManagementUtility::addTcaSelectItem(
            $table,
            'doktype',
            [
                $label,
                $doktype,
            ],
            '1',
            'after'
        );

        $iconIdentifier = $configuration['iconIdentifier'] ?? 'page-type-' . str_replace('_', '-',
                GeneralUtility::camelCaseToLowerCaseUnderscored($configuration['name']));

        $icons = [
            $doktype => $iconIdentifier,
        ];

        foreach (self::ICON_SUFFIXES as $suffix) {
            if ($this->iconRegistry->isRegistered($iconIdentifier . $suffix)) {
                $icons[$doktype . $suffix] = $iconIdentifier . $suffix;
            }
        }

        Typo3CoreArrayUtility::mergeRecursiveWithOverrule(
            $GLOBALS['TCA'][$table],
            [
                // add icons for new page type:
                'ctrl'  => [
                    'typeicon_classes' => $icons,
                ],
                // add all page standard fields and tabs to your new page type
                'types' => [
                    $doktype => [
                        'showitem' => $GLOBALS['TCA'][$table]['types'][PageRepository::DOKTYPE_DEFAULT]['showitem'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param array  $controllerCollection
     * @param string $collectMode
     * @param string $pluginName
     *
     * @return array
     */
    private function collectActionsAndConfiguration(
        array $controllerCollection,
        string $collectMode,
        string $pluginName = ''
    ): array {
        $configuration = [];
        $controllersAndCachedActions = [];
        $controllersAndUncachedActions = [];
        $annotationReader = new IndexedReader(new AnnotationReader());
        $extensionInformationService = GeneralUtility::makeInstance(ExtensionInformationService::class);

        foreach ($controllerCollection as $key => $value) {
            if (is_int($key)) {
                $controllerClassName = $value;
            } else {
                $controllerClassName = $key;
                $specifiedActions = $value;
            }

            $controller = GeneralUtility::makeInstance(ReflectionClass::class, $controllerClassName);
            $controllersAndCachedActions[$controllerClassName] = [];

            if (self::COLLECT_MODES['REGISTER_PLUGINS'] !== $collectMode) {
                $methods = $controller->getMethods();

                foreach ($methods as $method) {
                    $methodName = $method->getName();

                    if (!StringUtility::endsWith($methodName, 'Action')
                        || StringUtility::beginsWith($methodName, 'initialize')
                        || in_array($method->getDeclaringClass()->getName(),
                            [AbstractModuleController::class, ActionController::class], true)
                    ) {
                        continue;
                    }

                    $actionName = mb_substr($methodName, 0, -6);

                    if (isset($specifiedActions) && !in_array($actionName, $specifiedActions, true)) {
                        continue;
                    }

                    /** @var ModuleAction|null $moduleAction */
                    $docComment = $annotationReader->getMethodAnnotations($method);

                    if (isset($docComment[ModuleAction::class])) {
                        /** @var ModuleAction $action */
                        $action = $docComment[ModuleAction::class];

                        if (true === $action->isDefault()) {
                            array_unshift($controllersAndCachedActions[$controllerClassName],
                                $actionName);
                        } else {
                            $controllersAndCachedActions[$controllerClassName][] = $actionName;
                        }
                    }

                    if (isset($docComment[PluginAction::class])) {
                        /** @var PluginAction $action */
                        $action = $docComment[PluginAction::class];

                        if (true === $action->isDefault()) {
                            array_unshift($controllersAndCachedActions[$controllerClassName],
                                $actionName);
                        } else {
                            $controllersAndCachedActions[$controllerClassName][] = $actionName;
                        }

                        if (self::COLLECT_MODES['CONFIGURE_PLUGINS'] === $collectMode
                            && true === $action->isUncached()) {
                            $controllersAndUncachedActions[$controllerClassName][] = $actionName;
                        }

                        if (isset($docComment[PageType::class])) {
                            $extensionInformation = $extensionInformationService->extractExtensionInformationFromClassName($controllerClassName);
                            /** @var PageType $pageType */
                            $pageType = $docComment[PageType::class];
                            $pageObjectConfiguration = GeneralUtility::makeInstance(PageObjectConfiguration::class);
                            $pageObjectConfiguration->setAction($actionName);
                            $pageObjectConfiguration->setCacheable($pageType->isCacheable());
                            $pageObjectConfiguration->setContentType($pageType->getContentType());
                            $pageObjectConfiguration->setController($controllerClassName);
                            $pageObjectConfiguration->setDisableAllHeaderCode($pageType->isDisableAllHeaderCode());
                            $pageObjectConfiguration->setExtensionName($extensionInformation['extensionName']);
                            $pageObjectConfiguration->setPluginName($pluginName);
                            $pageObjectConfiguration->setTypeNum($pageType->getTypeNum());
                            $typoScriptObjectName = strtolower(implode('_', [
                                'ajax',
                                $extensionInformation['vendorName'],
                                $extensionInformation['extensionName'],
                                $pageObjectConfiguration->getController(),
                                $actionName,
                            ]));
                            $pageObjectConfiguration->setTypoScriptObjectName($typoScriptObjectName);
                            $pageObjectConfiguration->setVendorName($extensionInformation['vendorName']);
                            TypoScriptUtility::registerPageType($pageObjectConfiguration);
                        }
                    }
                }
            }

            Typo3CoreArrayUtility::mergeRecursiveWithOverrule($configuration,
                $annotationReader->getClassAnnotations($controller));
        }

        if (self::COLLECT_MODES['REGISTER_PLUGINS'] !== $collectMode) {
            array_walk($controllersAndCachedActions, static function (&$value) {
                $value = implode(', ', $value);
            });

            if (self::COLLECT_MODES['CONFIGURE_PLUGINS'] === $collectMode) {
                array_walk($controllersAndUncachedActions, static function (&$value) {
                    $value = implode(', ', $value);
                });
            }
        }

        switch ($collectMode) {
            case self::COLLECT_MODES['CONFIGURE_PLUGINS']:
                return [$configuration, $controllersAndCachedActions, $controllersAndUncachedActions];
            case self::COLLECT_MODES['REGISTER_MODULES']:
                return [$configuration, $controllersAndCachedActions];
            case self::COLLECT_MODES['REGISTER_PLUGINS']:
                return [$configuration];
            default:
                throw new InvalidArgumentException(__CLASS__ . ': $collectMode has to be a value defined in the constant COLLECT_MODES, but was "' . $collectMode . '"!',
                    1559627862);
        }
    }
}
