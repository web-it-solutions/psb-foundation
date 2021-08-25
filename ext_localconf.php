<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
defined('TYPO3_MODE') or die();

(static function () {
    /*
     * It is necessary to force a clear registration state for the GlobalVariableService because the InstallUtility
     * causes a second execution of all ext_localconf.php-files. This always happens when an extension is installed.
     * @see \TYPO3\CMS\Extensionmanager\Utility\InstallUtility, line 349
     */
    \PSB\PsbFoundation\Service\GlobalVariableService::clearRegistration();
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\EarlyAccessConstantsProvider::class);
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\FrontendUserProvider::class);
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\RequestParameterProvider::class);
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\SiteConfigurationProvider::class);

    // configure all plugins of those extensions which provide an ExtensionInformation-class and add TypoScript if missing
    $extensionInformationService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\PSB\PsbFoundation\Service\ExtensionInformationService::class);
    $iconService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\PSB\PsbFoundation\Service\Configuration\IconService::class);
    $registrationService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\PSB\PsbFoundation\Service\Configuration\RegistrationService::class);
    $allExtensionInformation = $extensionInformationService->getExtensionInformation();

    foreach ($allExtensionInformation as $extensionInformation) {
        $iconService->registerIconsFromExtensionDirectory($extensionInformation->getExtensionKey());
        $registrationService->configurePlugins($extensionInformation);
        \PSB\PsbFoundation\Utility\TypoScript\TypoScriptUtility::addDefaultTypoScriptForPluginsAndModules($extensionInformation);

        $userTsConfigFilename = 'EXT:' . $extensionInformation->getExtensionKey() . '/Configuration/TSConfig/UserTS.tsconfig';

        if (\PSB\PsbFoundation\Utility\FileUtility::fileExists($userTsConfigFilename)) {
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
             <INCLUDE_TYPOSCRIPT: source="FILE:' . $userTsConfigFilename . '">
        ');
        }
    }
})();
