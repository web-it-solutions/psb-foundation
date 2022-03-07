<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
defined('TYPO3_MODE') or die();

(static function () {
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\EarlyAccessConstantsProvider::class);
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\RequestParameterProvider::class);
    \PSB\PsbFoundation\Service\GlobalVariableService::registerGlobalVariableProvider(\PSB\PsbFoundation\Service\GlobalVariableProviders\SiteConfigurationProvider::class);

    // configure all plugins of those extensions which provide an ExtensionInformation-class and add TypoScript if missing
    $extensionInformationService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\PSB\PsbFoundation\Service\ExtensionInformationService::class);
    $registrationService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\PSB\PsbFoundation\Service\Configuration\RegistrationService::class);
    $allExtensionInformation = $extensionInformationService->getExtensionInformation();

    foreach ($allExtensionInformation as $extensionInformation) {
        $registrationService->configurePlugins($extensionInformation);
        \PSB\PsbFoundation\Utility\TypoScript\TypoScriptUtility::addDefaultTypoScriptForPluginsAndModules($extensionInformation);

        $pageTsConfigFilename = 'EXT:' . $extensionInformation->getExtensionKey() . '/Configuration/TSconfig/Page.tsconfig';

        if (\PSB\PsbFoundation\Utility\FileUtility::fileExists($pageTsConfigFilename)) {
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
             @import \'' . $pageTsConfigFilename . '\'
        ');
        }

        $userTsConfigFilename = 'EXT:' . $extensionInformation->getExtensionKey() . '/Configuration/TSconfig/User.tsconfig';

        if (\PSB\PsbFoundation\Utility\FileUtility::fileExists($userTsConfigFilename)) {
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addUserTSConfig('
             @import \'' . $userTsConfigFilename . '\'
        ');
        }
    }
})();
