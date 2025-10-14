<?php
declare(strict_types=1);

namespace T3\PwComments\Update;

use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use function sprintf;
use function str_contains;

#[UpgradeWizard('pwCommentsMigratePluginsWizard')]
class MigratePluginsUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private OutputInterface $output;

    public function __construct(private readonly QueryBuilder $queryBuilder, private readonly FlexFormService $flexFormService)
    {
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Migrate pw_comments plugins';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Since switchable controller actions have been removed existing plugins have to be migrated to the split individual plugins.';
    }

    /**
     * @inheritDoc
     */
    public function executeUpdate(): bool
    {
        $records = $this->queryBuilder
            ->select('uid', 'list_type', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $this->queryBuilder->expr()->eq('list_type', $this->queryBuilder->createNamedParameter('pwcomments_pi1')),
                $this->queryBuilder->expr()->or(
                    $this->queryBuilder->expr()->like('pi_flexform', $this->queryBuilder->createNamedParameter('%index%')),
                    $this->queryBuilder->expr()->like('pi_flexform', $this->queryBuilder->createNamedParameter('%new%')),
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $updatedRecords = 0;
        foreach ($records as $record) {
            $flexForm = GeneralUtility::xml2array($record['pi_flexform'] ?? '');
            $switchableControllerActions = $flexForm['data']['sDEF']['lDEF']['switchableControllerActions']['vDEF'] ?? null;
            if ($switchableControllerActions === null) {
                continue;
            }

            $plugin = 'show';
            if (str_contains($switchableControllerActions, 'new')) {
                $plugin = 'new';
            }

            $listType = 'pwcomments_' . $plugin;

            $this->queryBuilder->resetWhere();
            $this->queryBuilder
                ->update('tt_content')
                ->set('pi_flexform', null)
                ->set('list_type', $listType)
                ->where(
                    $this->queryBuilder->expr()->eq('uid', $record['uid'])
                )
                ->executeStatement();
            ++$updatedRecords;
        }

        $this->output->writeln(sprintf('%d records updated', $updatedRecords));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateNecessary(): bool
    {
        $count = $this->queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $this->queryBuilder->expr()->eq('list_type', $this->queryBuilder->createNamedParameter('pwcomments_pi1')),
                $this->queryBuilder->expr()->or(
                    $this->queryBuilder->expr()->like('pi_flexform', $this->queryBuilder->createNamedParameter('%index%')),
                    $this->queryBuilder->expr()->like('pi_flexform', $this->queryBuilder->createNamedParameter('%new%')),
                )
            )
            ->executeQuery()
            ->columnCount();

        return $count > 0;
    }

    /**
     * @inheritDoc
     */
    public function getPrerequisites(): array
    {
        return [];
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }
}
