<?php
namespace T3\PwComments\XClass;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2018 Armin Vieweg <armin@v.ieweg.de>
 */
use T3\PwComments\Utility\DatabaseUtility;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * XClass for PageLayoutController
 *
 * @package T3\PwComments
 */
class PageLayoutController extends \TYPO3\CMS\Backend\Controller\PageLayoutController
{
    /**
     * Generate the flashmessages for current pid
     *
     * @return string HTML content with flashmessages
     */
    protected function getHeaderFlashMessagesForCurrentPid(): string
    {
        $content = parent::getHeaderFlashMessagesForCurrentPid();

        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $total = $queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->where('pid = :pageUid')->setParameter('pageUid', $this->pageinfo['uid'])
            ->execute()
            ->fetchColumn();


        if (!$total) {
            return $content;
        }

        $queryBuilder = $connectionPool->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $released = $queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->where('pid = :pageUid')->setParameter('pageUid', $this->pageinfo['uid'])
            ->execute()
            ->fetchColumn();

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
                : $this->translate('unreleasedCommentsAmount', [$unreleased]);
            $textUnreleased = '<br><b>' . $textUnreleased . '</b>';
        }

        $path = self::getModuleUrl('web_list', [
            'id' => $this->pageinfo['uid'],
            'table' => 'tx_pwcomments_domain_model_comment',
            'imagemode' => 1
        ]);

        $message = '<a class="btn btn-warning pull-right" href="' . $path . '">' .
            $this->translate('showComments') . '</a><p>' . $textTotal . ' ' . $textUnreleased . '</p>';

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

    /**
     * Returns the URL to a given module
     *
     * @param string $moduleName Name of the module
     * @param array $urlParameters URL parameters that should be added as key value pairs
     * @return string Calculated URL
     * @throws RouteNotFoundException
     */
    protected static function getModuleUrl($moduleName, $urlParameters = []) : string
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        try {
            $uri = $uriBuilder->buildUriFromRoute($moduleName, $urlParameters);
        } catch (RouteNotFoundException $e) {
            $uri = static::isTypo3Version()
                ? $uriBuilder->buildUriFromRoutePath($moduleName, $urlParameters)
                : $uriBuilder->buildUriFromModule($moduleName, $urlParameters);
        }
        return (string) $uri;
    }

    /**
     * Checks if current TYPO3 version is 9.0.0 or greater (by default)
     *
     * @param string $version e.g. 9.0.0
     * @return bool
     */
    protected static function isTypo3Version($version = '9.0.0') : bool
    {
        return VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >=
            VersionNumberUtility::convertVersionNumberToInteger($version);
    }
}
