<?php
namespace T3\PwComments\Hooks;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2021 Armin Vieweg <armin@v.ieweg.de>
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * PageLayoutView Hook
 *
 * @package T3\PwComments
 */
class PageLayoutView
{
    const LLPATH = 'LLL:EXT:pw_comments/Resources/Private/Language/locallang_db.xlf:';

    /**
     * Returns information about this extension's pi1 plugin
     *
     * @param array $params Parameters to the hook
     * @return string Information about pi1 plugin
     */
    public function getExtensionSummary(array $params)
    {
        $result = '<strong>pw_comments</strong><br>';
        $result .= $this->getLanguageService()->sL(self::LLPATH . 'plugin.mode') . ': ';

        $flexformData = GeneralUtility::xml2array($params['row']['pi_flexform']);
        if (is_array($flexformData)) {
            $action = $flexformData['data']['sDEF']['lDEF']['switchableControllerActions']['vDEF'];
            $mode = StringUtility::beginsWith($action, 'Comment->index') ? 'index' : 'new';
            $result .= $this->getLanguageService()->sL(self::LLPATH . 'plugin.mode.' . $mode);
        }
        return $result;
    }

    /**
     * Return language service instance
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
