<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE === 'BE') {

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(
		'web_info',
		\Colorcube\ContentOverview\Controller\ContentOverviewController::class,
		NULL,
		'LLL:EXT:content_overview/Resources/Private/Language/locallang_content_overview.xlf:mod_tx_cms_webinfo_content_overview'
	);
}

?>