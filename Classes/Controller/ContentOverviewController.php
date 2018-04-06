<?php
namespace Colorcube\ContentOverview\Controller;

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

use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class for displaying a page tree with it's content elements
 */
class ContentOverviewController extends \TYPO3\CMS\Backend\Module\AbstractFunctionModule {

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * Constructor
     */
    public function __construct()
    {
        if (isset($GLOBALS['BE_USER']->uc['titleLen']) && $GLOBALS['BE_USER']->uc['titleLen'] > 0) {
            $this->fixedL = $GLOBALS['BE_USER']->uc['titleLen'];
        }
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

	/**
	 * Returns the menu array
	 *
	 * @return array
	 */
	public function modMenu() {
		$lang = $this->getLanguageService();

		$menuItems = array();

		// Page tree depth
		$menuItems['depth'] = array(
			0 => $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_0'),
			1 => $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_1'),
			2 => $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_2'),
			3 => $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_3'),
			999 => $lang->sL('LLL:EXT:lang/locallang_core.xlf:labels.depth_infi')
		);

		// Languages
		$languages = $this->getSystemLanguages();

		$menuItems['lang'][-1] = '[All]';
		foreach ($languages as $langRec) {
			$menuItems['lang'][$langRec['uid']] = $langRec['title'];
		}

		// Content types
		$ctypes = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'];

		$menuItems['ctype'][0] = '[All]';
		foreach($ctypes as $p)	{
			$menuItems['ctype'][$p[1]] = $lang->sL($p[0]);
		}

		return $menuItems;
	}

	/**
	 * MAIN function for page information of localization
	 *
	 * @return string Output HTML for the module.
	 */
	public function main() {
		$theOutput = $this->pObj->doc->header($this->getLanguageService()->sL('LLL:EXT:content_overview/Resources/Private/Language/locallang_content_overview.xlf:mod_tx_cms_webinfo_content_overview'));
		if ($this->pObj->id) {
			// Depth selector:
			$theOutput .= '<div class="form-inline form-inline-spaced">';
			$h_func = BackendUtility::getDropdownMenu($this->pObj->id, 'SET[depth]', $this->pObj->MOD_SETTINGS['depth'], $this->pObj->MOD_MENU['depth']);
			$h_func .= BackendUtility::getDropdownMenu($this->pObj->id, 'SET[lang]', $this->pObj->MOD_SETTINGS['lang'], $this->pObj->MOD_MENU['lang']);
			$h_func .= BackendUtility::getDropdownMenu($this->pObj->id, 'SET[ctype]', $this->pObj->MOD_SETTINGS['ctype'], $this->pObj->MOD_MENU['ctype']);
			$theOutput .= $h_func;
			// Add CSH:
			$theOutput .= BackendUtility::cshItem('_MOD_web_info', 'lang', NULL, '|<br />');
			$theOutput .= '</div>';
			// Showing the tree:
			// Initialize starting point of page tree:
			$treeStartingPoint = (int)$this->pObj->id;
			$treeStartingRecord = BackendUtility::getRecordWSOL('pages', $treeStartingPoint);
			$depth = $this->pObj->MOD_SETTINGS['depth'];
			// Initialize tree object:
			$tree = GeneralUtility::makeInstance(PageTreeView::class);
			$tree->init('AND ' . $this->getBackendUser()->getPagePermsClause(1));
$tree->addField('l18n_cfg');
			// Creating top icon; the current page
			$HTML =$this->iconFactory->getIconForRecord('pages', $treeStartingRecord, Icon::SIZE_SMALL)->render();
			$tree->tree[] = array(
				'row' => $treeStartingRecord,
				'HTML' => $HTML
			);
			// Create the tree from starting point:
			if ($depth) {
				$tree->getTree($treeStartingPoint, $depth, '');
			}
			// Render information table:
			$theOutput .= $this->renderOverviewTable($tree, $depth);

			$theOutput .= '<p>' . count($tree->ids) . ' Pages:<br />' . implode(', ', $tree->ids) . '</p>';
		}
		return $theOutput;
	}

	/**
	 * Rendering the localization information table.
	 *
	 * @param array $tree The Page tree data
	 * @return string HTML for the localization information table.
	 */
	public function renderOverviewTable(&$tree, $depth) {
		$lang = $this->getLanguageService();
		// System languages retrieved:
		$languages = $this->getSystemLanguages();
		// Title length:
		$titleLen = $this->getBackendUser()->uc['titleLen'];
		// Put together the TREE:
		$output = '';
		$newOL_js = array();
		foreach ($tree->tree as $data) {
			$tCells = array();

			// Page tree

            $pageTitleHsc = $this->linkEditContent(BackendUtility::getRecordTitle('pages', $data['row'], true), $data['row']);

			// Page tree column
			$tCells[] =
                '<td>' . $data['depthData'] .

                BackendUtility::wrapClickMenuOnIcon($data['HTML'], 'pages', $data['row']['uid']) .
                    '<a href="#" onclick="' . htmlspecialchars(
                        'top.loadEditId(' . (int)$data['row']['uid'] . ',"&SET[language]=0"); return false;'
                    ) . '" title="' . $lang->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_editPage') . '">' .
                $pageTitleHsc .
                    '</a>' .
				'</td>';



			$modSharedTSconfig = BackendUtility::getModTSconfig($data['row']['uid'], 'mod.SHARED');
			$disableLanguages = isset($modSharedTSconfig['properties']['disableLanguages']) ? GeneralUtility::trimExplode(',', $modSharedTSconfig['properties']['disableLanguages'], TRUE) : array();

			// Traverse system languages
			foreach ($languages as $langRow) {

				if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === (int)$langRow['uid']) {

                    if (0 == (int)$langRow['uid']) {
                        $row = $data['row'];
                    } else {
                        $row = $this->getLangStatus($data['row']['uid'], $langRow['uid']);
                    }

					if (is_array($row)) {

                        $tableName = (0 == (int)$langRow['uid']) ? 'pages' : 'pages_language_overlay';
                        $pageTitleHsc = $this->linkEditContent(BackendUtility::getRecordTitle($tableName, $row, true), $row);

                        // Edit links
                        $info = '';
                        $params = '&edit[' . $tableName . '][' . $row['uid'] . ']=edit';
                        $info .= '<a href="#" onclick="' . htmlspecialchars(BackendUtility::editOnClick($params))
                            . '" title="' . $lang->sL(
                                'LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_editDefaultLanguagePage'
                            ) . '">' . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL)->render() . '</a>';

                        // "View page" link
                        $viewPageLink = '<a href="#" onclick="' . htmlspecialchars(BackendUtility::viewOnClick(
                                $row['uid'], $GLOBALS['BACK_PATH'], '', '', '', '&L='.$langRow['uid'])
                            ) . '" title="' . $lang->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_viewPage') . '">' .
                            $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL)->render() . '</a>';


                        $icon = $this->getIcon($tableName, $row);
                        $tCells[] =
                            '<td class="col-border-left" colspan="2">' .
                            '<div style="float:right; display:inline-block">' . $info . $viewPageLink . '</div>' .
                            BackendUtility::wrapClickMenuOnIcon($icon, $tableName, $row['uid']) . $pageTitleHsc .
                            '</td>';


					} else {
						$tCells[] = '<td class="col-border-left" colspan="2">&nbsp;</td>';
					}
				}
			}
			$output .= '
				<tr>
					' . implode('
					', $tCells) . '
				</tr>';


            // create tt_content listing

            $overviewData = $this->getContentOverview($data['row'], $languages);
            $maxCount = $overviewData['maxCount'];
            $overviewContent = $overviewData['content'];

            for ($i = 0; $i < $maxCount; $i++) {
                $tCells = array();
                $tCells[] = '<td>' . $data['depthData'] .
						($data['isLast'] ? '' : '<span class="treeline-icon treeline-icon-line"></span>').
						(($data['hasSub'] && $data['invertedDepth'] > 1) ? '<span class="treeline-icon treeline-icon-line"></span>' : '').
					'</td>';
                $tColspan = '';
                foreach ($languages as $sysLang => $langRow) {
                    if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === $sysLang) {
                        $tCells[] = '<td class="col-border-left">' . $overviewContent[$sysLang][$i]['c'] . '</td>';
                        $tCells[] = '<td>' . $overviewContent[$sysLang][$i]['t'] . '</td>';
                    }
                }
                $output .= '
				<tr>
					' . implode('
					', $tCells) . '
				</tr>';
            }

		}

		// table header

		$tCells = array();

		$tCells[] = '<td>' . $lang->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_page') . ':</td>';

		foreach ($languages as $langRow) {

			if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === (int)$langRow['uid']) {
                $tCells[] = '<td class="col-border-left" colspan="2">' . htmlspecialchars($langRow['title']) . '</td>';
			}
		}

		$output =
			'<div class="table-fit">' .
				'<table class="table table-striped table-hover" id="contentOverviewTable">' .
					'<thead>' .
						'<tr>' .
							implode('', $tCells) .
						'</tr>' .
					'</thead>' .
					'<tbody>' .
						$output .
					'</tbody>' .
				'</table>' .
			'</div>';
		return $output;
	}



    public function getContentOverview($pageItem, $languages)	{

        $maxCount = 0;
        $table = array();

        // Traverse system languages:
        foreach ($languages as $langRow) {

            $sysLang = (int)$langRow['uid'];

            if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === $sysLang) {

                $whereClause =  ' AND sys_language_uid=' . (int)$sysLang;
                if ($this->pObj->MOD_SETTINGS['ctype'] != '0') {
                    $whereClause .= ' AND CType=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->pObj->MOD_SETTINGS['ctype'], 'tt_content');
                }

#$count = $this->getDatabaseConnection()->exec_SELECTcountRows('uid', 'tt_content', 'pid=' . (int)$pageId . ' AND sys_language_uid=' . (int)$sysLang . BackendUtility::deleteClause('tt_content') . BackendUtility::versioningPlaceholderClause('tt_content'));
#TODO check versioning etc
                $rows = BackendUtility::getRecordsByField('tt_content', 'pid', $pageItem['uid'], $whereClause, $groupBy = '', $orderBy = 'sorting', $limit = '', $useDeleteClause = true);

                $table[$sysLang] = array();

                if ($rows) {

                    $maxCount = max($maxCount, count($rows));

                    foreach ($rows as $row) {

                        $icon = $this->getIcon('tt_content', $row);

                        $info = $icon . $this->linkEditContent(BackendUtility::getRecordTitle('tt_content', $row, true), $row);

                        $typeInfo = '';
                        if ($row['CType'] === 'list') {
                            $pluginName = BackendUtility::getLabelFromItemlist('tt_content', 'list_type', $row['list_type']);
                            $typeInfo = $this->getLanguageService()->sL($pluginName, true) . ' (' . $row['list_type'] . ')';
                        } else {

                            $CTypeName = BackendUtility::getLabelFromItemlist('tt_content', 'CType', $row['CType']);
                            $typeInfo = $this->getLanguageService()->sL($CTypeName, true) . ' (' . $row['CType'] . ')';
                        }

                        $table[$sysLang][] = array(
                            'c' => $info,
                            't' => htmlspecialchars($typeInfo)
                        );

                    }
                }
            }

        }

        return array(
            'maxCount' => $maxCount,
            'content' => $table
        );
    }

    /**
     * Creates the icon image tag for record from table and wraps it in a link which will trigger the click menu.
     *
     * @param string $table Table name
     * @param array $row Record array
     * @return string HTML for the icon
     */
    public function getIcon($table, $row) {

        // The icon with link
        #$altText = BackendUtility::getRecordIconAltText($row, $table);
        $toolTip = BackendUtility::getRecordToolTip($row, $table);
        $additionalStyle = $indent ? ' style="margin-left: ' . $indent . 'px;"' : '';
        $iconImg = '<span ' . $toolTip . ' ' . $additionalStyle . '>'
            . $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render()
            . '</span>';
        $icon = $this->getBackendUser()->recordEditAccessInternals($table, $row) ? BackendUtility::wrapClickMenuOnIcon($iconImg, $table, $row['uid']) : $iconImg;


        return $icon;
    }

    /**
     * Will create a link on the input string and possibly a big button after the string which links to editing in the RTE.
     * Used for content element content displayed so the user can click the content / "Edit in Rich Text Editor" button
     *
     * @param string $str String to link. Must be prepared for HTML output.
     * @param array $row The row.
     * @return string If the whole thing was editable ($this->doEdit) $str is return with link around. Otherwise just $str.
     * @see getTable_tt_content()
     */
    public function linkEditContent($str, $row) {
        $addButton = '';
        $onClick = '';
        if ($this->getBackendUser()->recordEditAccessInternals('tt_content', $row)) {
            // Setting onclick action for content link:
            $onClick = BackendUtility::editOnClick('&edit[tt_content][' . $row['uid'] . ']=edit');
        }
        // Return link
        return $onClick ? '<a href="#" onclick="' . htmlspecialchars($onClick)
            . '" title="' . $this->getLanguageService()->getLL('edit', TRUE) . '">' . $str . '</a>' . $addButton : $str;
    }


	/**
	 * Selects all system languages (from sys_language)
	 *
	 * @return array System language records in an array.
	 */
	public function getSystemLanguages() {

        $allowed_languages= [];
		if (!$this->getBackendUser()->user['admin'] && $this->getBackendUser()->groupData['allowed_languages'] !== '') {
			$allowed_languages = array_flip(explode(',', $this->getBackendUser()->groupData['allowed_languages']));
		}
		$res = $this->getDatabaseConnection()->exec_SELECTquery('*', 'sys_language', '1=1' . BackendUtility::deleteClause('sys_language'));
		$languagesList = array();
		while ($row = $this->getDatabaseConnection()->sql_fetch_assoc($res)) {
			if (is_array($allowed_languages) && !empty($allowed_languages)) {
				if (isset($allowed_languages[$row['uid']])) {
					$languagesList[$row['uid']] = $row;
				}
			} else {
				$languagesList[$row['uid']] = $row;
			}
		}
		$this->getDatabaseConnection()->sql_free_result($res);

        if (!isset($languagesList[0])) {
            $languagesList[0] = array(
                'uid' => 0,
#FIXME get tsconfig lang name
                'title' => $this->getLanguageService()->sL(
                        'LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_default'
                    )
            );
        }

        ksort($languagesList);

		return $languagesList;
	}

	/**
	 * Get an alternative language record for a specific page / language
	 *
	 * @param int $pageId Page ID to look up for.
	 * @param int $langId Language UID to select for.
	 * @return array pages_languages_overlay record
	 */
	public function getLangStatus($pageId, $langId) {
		$res = $this->getDatabaseConnection()->exec_SELECTquery(
			'*',
			'pages_language_overlay',
			'pid=' . (int)$pageId .
				' AND sys_language_uid=' . (int)$langId .
				BackendUtility::deleteClause('pages_language_overlay') .
				BackendUtility::versioningPlaceholderClause('pages_language_overlay')
		);
		$row = $this->getDatabaseConnection()->sql_fetch_assoc($res);
		BackendUtility::workspaceOL('pages_language_overlay', $row);
		if (is_array($row)) {
			$row['_COUNT'] = $this->getDatabaseConnection()->sql_num_rows($res);
			$row['_HIDDEN'] = $row['hidden'] || (int)$row['endtime'] > 0 && (int)$row['endtime'] < $GLOBALS['EXEC_TIME'] || $GLOBALS['EXEC_TIME'] < (int)$row['starttime'];
		}
		return $row;
	}

	/**
	 * Counting content elements for a single language on a page.
	 *
	 * @param int $pageId Page id to select for.
	 * @param int $sysLang Sys language uid
	 * @return int Number of content elements from the PID where the language is set to a certain value.
	 */
	public function getContentElementCount($pageId, $sysLang) {
		$count = $this->getDatabaseConnection()->exec_SELECTcountRows('uid', 'tt_content', 'pid=' . (int)$pageId . ' AND sys_language_uid=' . (int)$sysLang . BackendUtility::deleteClause('tt_content') . BackendUtility::versioningPlaceholderClause('tt_content'));
		return $count ?: '-';
	}

    /**
     * @return PageLayoutController
     */
    protected function getPageLayoutController() {
        return $GLOBALS['SOBE'];
    }


    /**
	 * Returns LanguageService
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

	/**
	 * Returns the database connection
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected function getDatabaseConnection() {
		return $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Returns the current BE user.
	 *
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

}
