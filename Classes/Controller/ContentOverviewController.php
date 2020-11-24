<?php

namespace Colorcube\ContentOverview\Controller;



use TYPO3\CMS\Backend\Controller\PageLayoutController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Info\Controller\InfoModuleController;

/**
 * Class for displaying a page tree with it's content elements
 */
class ContentOverviewController
{

    /**
     * @var int Value of the GET/POST var 'id'
     */
    protected $id;

    /**
     * @var InfoModuleController Contains a reference to the parent calling object
     */
    protected $pObj;

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
     * Init, called from parent object
     *
     * @param InfoModuleController $pObj A reference to the parent (calling) object
     */
    public function init($pObj)
    {
        $this->pObj = $pObj;
        $this->id = (int)GeneralUtility::_GP('id');
        // Setting MOD_MENU items as we need them for logging:
        $this->pObj->MOD_MENU = array_merge($this->pObj->MOD_MENU, $this->modMenu());
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Returns the menu array
     *
     * @return array
     */
    public function modMenu()
    {
        $lang = $this->getLanguageService();

        $menuItems = array();

        // Page tree depth
        $menuItems['depth'] = array(
            0 => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_0'),
            1 => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_1'),
            2 => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_2'),
            3 => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_3'),
            4 => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_4'),
            999 => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.depth_infi')
        );

        // Languages
        $languages = $this->getSystemLanguages();

        $menuItems['lang'][-1] = $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages');
        foreach ($languages as $langRec) {
            $menuItems['lang'][$langRec['uid']] = $langRec['title'];
        }

        // Content types
        $ctypes = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'];

        $menuItems['ctype'][0] = '[All]';
        $menuItems['ctype'][1] = '[Pages only]';
        foreach ($ctypes as $p) {
            $menuItems['ctype'][$p[1]] = $lang->sL($p[0]);
        }

        return $menuItems;
    }

    /**
     * MAIN function for page information of localization
     *
     * @return string Output HTML for the module.
     */
    public function main()
    {
        $theOutput = '<h1>' . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:content_overview/Resources/Private/Language/locallang_content_overview.xlf:mod_tx_cms_webinfo_content_overview')) . '</h1>';
        if ($this->id) {
            // Depth selector:
            $theOutput .= '<div class="form-inline form-inline-spaced">';
            $h_func = BackendUtility::getDropdownMenu($this->id, 'SET[depth]', $this->pObj->MOD_SETTINGS['depth'], $this->pObj->MOD_MENU['depth']);
            $h_func .= BackendUtility::getDropdownMenu($this->id, 'SET[lang]', $this->pObj->MOD_SETTINGS['lang'], $this->pObj->MOD_MENU['lang']);
            $h_func .= BackendUtility::getDropdownMenu($this->id, 'SET[ctype]', $this->pObj->MOD_SETTINGS['ctype'], $this->pObj->MOD_MENU['ctype']);
            $theOutput .= $h_func;
            $theOutput .= '</div>';
            // Showing the tree:
            // Initialize starting point of page tree:
            $treeStartingPoint = (int)$this->id;
            $treeStartingRecord = BackendUtility::getRecordWSOL('pages', $treeStartingPoint);
            $depth = $this->pObj->MOD_SETTINGS['depth'];
            // Initialize tree object:
            $tree = GeneralUtility::makeInstance(PageTreeView::class);
            $tree->init('AND ' . $this->getBackendUser()->getPagePermsClause(1));
            $tree->addField('slug');
            $tree->addField('l18n_cfg');
            // Creating top icon; the current page
            $HTML = $this->iconFactory->getIconForRecord('pages', $treeStartingRecord, Icon::SIZE_SMALL)->render();
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
     * @param PageTreeView $tree The Page tree data
     * @return string HTML for the localization information table.
     */
    public function renderOverviewTable($tree, $depth)
    {
        $lang = $this->getLanguageService();
        // System languages retrieved:
        $languages = $this->getSystemLanguages();
        // Title length:
        $titleLen = $this->getBackendUser()->uc['titleLen'];
        // Put together the TREE:
        $output = '';
        foreach ($tree->tree as $data) {
            $tCells = array();

            // Page tree

            $pageTitleHsc = $this->linkEditContent(BackendUtility::getRecordTitle('pages', $data['row'], true), $data['row'], 'pages');

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



            $modSharedTSconfig = BackendUtility::getPagesTSconfig($data['row']['uid'], 'mod.SHARED');
            $disableLanguages = isset($modSharedTSconfig['properties']['disableLanguages']) ? GeneralUtility::trimExplode(',', $modSharedTSconfig['properties']['disableLanguages'], TRUE) : array();

            // Traverse system languages
            foreach ($languages as $langRow) {


                if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === (int)$langRow['uid']) {

                    $row = $data['row'];

                    if (0 == (int)$langRow['uid']) {
                        $row = $data['row'];
                    } else {
                        $row = $this->getLangStatus($data['row']['uid'], $langRow['uid']);
                    }

                    if (is_array($row)) {

                        // "Edit page" link
                        $urlParameters = [
                            'edit' => [
                                'pages' => [
                                    $row['uid'] => 'edit'
                                ]
                            ],
                            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
                        ];
                        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                        $editUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', $urlParameters);
                        $editPageLink = '<a class="btn btn-default" href="' . htmlspecialchars($editUrl)
                            . '" title="' . $lang->sL(
                                'LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_editDefaultLanguagePage'
                            ) . '">' . $this->iconFactory->getIcon('actions-document-open', Icon::SIZE_SMALL)->render() . '</a>';

                        // "View page" link

                        $languageParameter = $row['sys_language_uid'] ? ('&L=' . $row['sys_language_uid']) : '';
                        $onClick = BackendUtility::viewOnClick($row['uid'], '', BackendUtility::BEgetRootLine($row['uid']), '', '', $languageParameter);
                        $viewPageLink = '<a class="btn btn-default" href="#" onclick="' . htmlspecialchars($onClick) . '" title="' . $lang->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_viewPage') . '">' .
                            $this->iconFactory->getIcon('actions-document-view', Icon::SIZE_SMALL)->render() . '</a>';

                        $tCells[] =
                            '<td class="col-border-left">' .  $viewPageLink . $editPageLink . '</td>';

                        $icon = $this->getIcon('pages', $row);
                        $pageTitleHsc = $this->linkEditContent(BackendUtility::getRecordTitle('pages', $row, true), $row, 'pages');
                        $tCells[] =
                            '<td>' . $icon . $pageTitleHsc . '</td>';

                        $tCells[] =
                            '<td class="col-border-left">' . htmlspecialchars($row['slug']). '</td>';

                    } else {
                        $tCells[] = '<td class="col-border-left" colspan="3">&nbsp;</td>';
                    }

                    $tCells[] = '<td class="col-border-left" colspan="2">&nbsp;</td>';
                }
            }
            $output .= '
				<tr>
					' . implode('
					', $tCells) . '
				</tr>';


            // create tt_content listing

            if ($this->pObj->MOD_SETTINGS['ctype'] != '1') {
                $overviewData = $this->getContentOverview($data['row'], $languages);
                $maxCount = $overviewData['maxCount'];
                $overviewContent = $overviewData['content'];

                for ($i = 0; $i < $maxCount; $i++) {
                    $tCells = array();
                    $tCells[] = '<td>' . $data['depthData'] .
                        ($data['isLast'] ? '' : '<span class="treeline-icon treeline-icon-line"></span>') .
                        (($data['hasSub'] && $data['invertedDepth'] > 1) ? '<span class="treeline-icon treeline-icon-line"></span>' : '') .
                        '</td>';
                    $tColspan = '';
//                    $tCells[] = '<td class="col-border-left" colspan="3">aa</td>';
                    foreach ($languages as $sysLang => $langRow) {
                        if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === $sysLang) {
                            $tCells[] = '<td class="col-border-left" colspan="3">&nbsp;</td>';
                            $tCells[] = '<td class="col-border-left">' . $overviewContent[$sysLang][$i]['c'] . '</td>';
                            $tCells[] = '<td class="col-border-left">' . $overviewContent[$sysLang][$i]['t'] . '</td>';
                        }
                    }
                    $output .= '
				<tr>
					' . implode('
					', $tCells) . '
				</tr>';
                }
            }

        }

        // table header

        $tCells = array();

        $tCells[] = '<td><strong>' . $lang->sL($GLOBALS['TCA']['pages']['columns']['title']['label']) . '</strong></td>';

        foreach ($languages as $langRow) {
            if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === (int)$langRow['uid']) {
                $tCells[] = '<td class="col-border-left" colspan="2"><strong>' . htmlspecialchars($langRow['title']) . '</strong></td>';
                $tCells[] = '<td class="col-border-left"><strong>' . $lang->sL($GLOBALS['TCA']['pages']['columns']['slug']['label']) . '</strong></td>';
                $tCells[] = '<td class="col-border-left"><strong>Record</strong></td>';
                $tCells[] = '<td class="col-border-left"><strong>Record Type</strong></td>';
            }
        }

        $output =
            '<div class="table-fit">' .
            '<table class="table table-striped table-hover typo3-page-pages" id="contentOverviewTable">' .
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


    public function getContentOverview($pageItem, $languages)
    {

        $maxCount = 0;
        $table = array();

        // Traverse system languages:
        foreach ($languages as $langRow) {

            $sysLang = (int)$langRow['uid'];

            if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === $sysLang) {

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
                $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, (int)$this->getBackendUser()->workspace));
                if (true) { // $showHidden
                    $queryBuilder->getRestrictions()
                        ->removeByType(HiddenRestriction::class)
                        ->removeByType(StartTimeRestriction::class)
                        ->removeByType(EndTimeRestriction::class);
                }
                $queryBuilder
                    ->select('*')
                    ->from('tt_content')
                    ->where(
                        $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageItem['uid'], \PDO::PARAM_INT)),
                        $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sysLang, \PDO::PARAM_INT))
                    )
                    ->orderBy('sorting');

                if ($this->pObj->MOD_SETTINGS['lang'] == -1 || (int)$this->pObj->MOD_SETTINGS['lang'] === $sysLang) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->eq(
                            'sys_language_uid',
                            $queryBuilder->createNamedParameter($sysLang, \PDO::PARAM_INT)
                        )
                    );
                }

                if ($this->pObj->MOD_SETTINGS['ctype']) {
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->eq(
                            'CType',
                            $queryBuilder->createNamedParameter($this->pObj->MOD_SETTINGS['ctype'], \PDO::PARAM_STR)
                        )
                    );
                }

                $rows = $queryBuilder->execute()->fetchAllAssociative();

                $table[$sysLang] = array();

                if ($rows) {

                    $maxCount = max($maxCount, count($rows));

                    foreach ($rows as $row) {

                        $icon = $this->getIcon('tt_content', $row);

                        $info = $icon . $this->linkEditContent(BackendUtility::getRecordTitle('tt_content', $row, true), $row, 'tt_content');

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
    public function getIcon($table, $row)
    {
        // The icon with link
        #$altText = BackendUtility::getRecordIconAltText($row, $table);
        $toolTip = BackendUtility::getRecordToolTip($row, $table);
        $iconImg = '<span ' . $toolTip . '>'
            . $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL)->render()
            . '</span>';
        $icon = $this->getBackendUser()->recordEditAccessInternals($table, $row) ? BackendUtility::wrapClickMenuOnIcon($iconImg, $table, $row['uid']) : $iconImg;

        return $icon;
    }

    /**
     * Will create a link on the input string
     *
     * @param string $str String to link. Must be prepared for HTML output.
     * @param array $row The row.
     */
    public function linkEditContent($str, $row, $table)
    {
        if ($this->getBackendUser()->recordEditAccessInternals('tt_content', $row)) {
            $urlParameters = [
                'edit' => [
                    $table => [
                        $row['uid'] => 'edit'
                    ]
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
            ];
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $editUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', $urlParameters);

            return '<a href="' . htmlspecialchars($editUrl) . '" title="' . $this->getLanguageService()->getLL('edit') . '">' . $str . '</a>';
        }
        return $str;
    }


    /**
     * Selects all system languages (from sys_language)
     *
     * @return array System language records in an array.
     */
    public function getSystemLanguages()
    {

        $allowed_languages = [];
        if (!$this->getBackendUser()->user['admin'] && $this->getBackendUser()->groupData['allowed_languages'] !== '') {
            $allowed_languages = array_flip(explode(',', $this->getBackendUser()->groupData['allowed_languages']));
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_language');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->select('uid', 'title')
            ->from('sys_language')
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)));
        $rows = array_merge(
//            [
//                [
//                    'uid' => -1,
//                    'title' => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages')
//                ]
//            ],
            [
                [
                    'uid' => 0,
                    'title' => $this->getLanguageService()->sL('LLL:EXT:info/Resources/Private/Language/locallang_webinfo.xlf:lang_renderl10n_default')
                ]
            ],
            $queryBuilder->execute()->fetchAllAssociative()
        );

        $languagesList = [];
        foreach ($rows as $row) {
            if (is_array($allowed_languages) && !empty($allowed_languages)) {
                if (isset($allowed_languages[$row['uid']])) {
                    $languagesList[$row['uid']] = $row;
                }
            } else {
                $languagesList[$row['uid']] = $row;
            }
        }

        return $languagesList;
    }

    /**
     * Get an alternative language record for a specific page / language
     *
     * @param int $pageId Page ID to look up for.
     * @param int $langId Language UID to select for.
     * @return array pages_languages_overlay record
     */
    public function getLangStatus($pageId, $langId)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $GLOBALS['TCA']['pages']['ctrl']['languageField'],
                    $queryBuilder->createNamedParameter($langId, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAssociative();
    }


    /**
     * @return PageLayoutController
     */
    protected function getPageLayoutController()
    {
        return $GLOBALS['SOBE'];
    }


    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

}
