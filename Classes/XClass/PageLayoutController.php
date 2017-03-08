<?php
namespace PwCommentsTeam\PwComments\XClass;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 */
use PwCommentsTeam\PwComments\Utility\DatabaseUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * XClass for PageLayoutController
 *
 * @package PwCommentsTeam\PwComments
 */
class PageLayoutController extends \TYPO3\CMS\Backend\Controller\PageLayoutController
{
    /**
     * Generate the flashmessages for current pid
     *
     * @return string HTML content with flashmessages
     */
    protected function getHeaderFlashMessagesForCurrentPid()
    {
        $content = parent::getHeaderFlashMessagesForCurrentPid();
        $total = DatabaseUtility::getDatabaseConnection()->exec_SELECTcountRows(
            'uid',
            'tx_pwcomments_domain_model_comment',
            'pid = ' . $this->pageinfo['uid'] . DatabaseUtility::getEnabledFields('tx_pwcomments_domain_model_comment', true)
        );
        if (!$total) {
            return $content;
        }

        $released = DatabaseUtility::getDatabaseConnection()->exec_SELECTcountRows(
            'uid',
            'tx_pwcomments_domain_model_comment',
            'pid = ' . $this->pageinfo['uid'] . DatabaseUtility::getEnabledFields('tx_pwcomments_domain_model_comment')
        );
        $unreleased = $total - $released;

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:backend/Resources/Private/Templates/InfoBox.html')
        );
        $title = 'pw_comments';

        $textTotal = $total == 1
            ? $this->translate('totalCommentsAmountOne')
            : $this->translate('totalCommentsAmount', [$total]);

        $textUnreleased = '';
        if ($unreleased > 0) {
            $textUnreleased = $unreleased == 1
                ? $this->translate('unreleasedCommentsAmountOne')
                : $this->translate('unreleasedCommentsAmount', [$total]);
            $textUnreleased = '<b>' . $textUnreleased . '</b>';
        }
        $message = '<p>' . $textTotal . ' ' . $textUnreleased . '</p>';

        $uriBuilder = new \TYPO3\CMS\Backend\Routing\UriBuilder();
        $path = $uriBuilder->buildUriFromModule('web_list', [
            'id' => $this->pageinfo['uid'],
            'table' => 'tx_pwcomments_domain_model_comment',
            'imagemode' => 1
        ]);

        $message .= '<a class="btn btn-warning" href="' . $path . '">' . $this->translate('showComments') . '</a>';
        $view->assignMultiple([
            'title' => $title,
            'message' => $message,
            'state' => \TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper::STATE_NOTICE
        ]);
        $content .= $view->render();
        return $content;
    }

    /**
     * Resolves given label to locallang.xlf of pw_comments
     *
     * @param string $label of translation
     * @param array $arguments
     * @return string Resolved translation
     */
    private function translate($label, array $arguments = [])
    {
        $translation = $this->getLanguageService()->sL(
            'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:' . $label
        );
        if (!empty($arguments)) {
            return vsprintf($translation, $arguments);
        }
        return $translation;
    }
}
