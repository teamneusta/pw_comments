<?php

declare(strict_types=1);

namespace T3\PwComments\Event\Listener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

final readonly class ModifyPageLayoutEventListener
{
    public function __construct(
        private LanguageService $languageService,
        private ConnectionPool $connectionPool,
        private ViewFactoryInterface $viewFactory,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $pageId = (int) ($event->getRequest()->getQueryParams()['id'] ?? 0);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $total = (int) ($queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->where('pid = :pageUid')->setParameter('pageUid', $pageId)
            ->executeQuery()
            ->fetchOne() ?: 0);

        if (!$total) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_pwcomments_domain_model_comment');
        $released = (int) ($queryBuilder
            ->count('uid')
            ->from('tx_pwcomments_domain_model_comment')
            ->where('pid = :pageUid')->setParameter('pageUid', $pageId)->executeQuery()
            ->fetchOne() ?: 0);

        $unreleased = $total - $released;

        $view = $this->viewFactory->create(
            new ViewFactoryData(
                templatePathAndFilename: GeneralUtility::getFileAbsFileName(
                    'EXT:backend/Resources/Private/Templates/InfoBox.fluid.html',
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

        $dispatchArgs = sprintf(
            'records,id=%d&table=%s&imagemode=1',
            $pageId,
            'tx_pwcomments_domain_model_comment',
        );

        $message = '<button type="button" class="btn btn-warning float-end"'
            . ' data-dispatch-action="TYPO3.ModuleMenu.showModule"'
            . ' data-dispatch-args-list="' . htmlspecialchars($dispatchArgs) . '">'
            . htmlspecialchars($this->translate('showComments'))
            . '</button><p>' . $textTotal . ' ' . $textUnreleased . '</p>';

        $view->assignMultiple([
            'title' => $title,
            'message' => $message,
            'state' => ContextualFeedbackSeverity::INFO,
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
            'LLL:EXT:pw_comments/Resources/Private/Language/locallang.xlf:' . $label,
        );
        if (!empty($arguments)) {
            return \vsprintf($translation, $arguments);
        }
        return $translation;
    }
}
