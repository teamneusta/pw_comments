<?php
declare(strict_types=1);

namespace T3\PwComments\Event\Listener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Fluid\ViewHelpers\Be\InfoboxViewHelper;
use function vsprintf;

final readonly class ModifyPageLayoutEventListener
{
    public function __construct(
        private LanguageService $languageService,
        private ConnectionPool $connectionPool,
        private ViewFactoryInterface $viewFactory,
    ) {
    }

    // @TODO: check if this still works after upgrade
    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $pageId = (int)($event->getRequest()->getQueryParams()['id'] ?? 0);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $total = (int)($queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->where('pid = :pageUid')->setParameter('pageUid', $pageId)
            ->executeQuery()
            ->fetchOne() ?: 0);


        if (!$total) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $released = (int)($queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->where('pid = :pageUid')->setParameter('pageUid', $pageId)->executeQuery()
            ->fetchOne() ?: 0);

        $unreleased = $total - $released;

        $view = $this->viewFactory->create(
            new ViewFactoryData(
                templatePathAndFilename: GeneralUtility::getFileAbsFileName(
                'EXT:backend/Resources/Private/Templates/InfoBox.html',
            ),
            ),
        );
        $title = 'pw_comments';

        $textTotal = $total === 1
            ? $this->translate('totalCommentsAmountOne')
            : $this->translate('totalCommentsAmount', [$total]);

        $textUnreleased = '';
        if ($unreleased > 0) {
            $textUnreleased = $unreleased === 1
                ? $this->translate('unreleasedCommentsAmountOne')
                : $this->translate('unreleasedCommentsAmount', [$unreleased]);
            $textUnreleased = '<br><b>' . $textUnreleased . '</b>';
        }

        $path = self::getModuleUrl('web_list', [
            'id' => $pageId,
            'table' => 'tx_pwcomments_domain_model_comment',
            'imagemode' => 1
        ]);

        $message = '<a class="btn btn-warning float-end" href="' . $path . '">' .
            $this->translate('showComments') . '</a><p>' . $textTotal . ' ' . $textUnreleased . '</p>';

        $view->assignMultiple([
            'title' => $title,
            'message' => $message,
            'state' => InfoboxViewHelper::STATE_INFO
        ]);

        $event->setHeaderContent($view->render());
    }

    /**
     * Resolves given label to locallang.xlf of pw_comments
     *
     * @param string $label of translation
     * @return string Resolved translation
     */
    private function translate($label, array $arguments = [])
    {
        $translation = $this->languageService->sL(
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
            $uri = $uriBuilder->buildUriFromRoutePath($moduleName, $urlParameters);
        }
        return (string) $uri;
    }
}
