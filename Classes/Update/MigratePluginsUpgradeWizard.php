<?php

declare(strict_types=1);

namespace T3\PwComments\Update;

use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('pwCommentsMigratePluginsWizard')]
class MigratePluginsUpgradeWizard implements UpgradeWizardInterface, ChattyInterface
{
    private const TABLE = 'tt_content';

    private OutputInterface $output;

    public function __construct(private readonly ConnectionPool $connectionPool) {}

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
        $queryBuilder = $this->getQueryBuilder();
        $records = $queryBuilder
            ->select('uid', 'list_type', 'pi_flexform')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter('pwcomments_pi1')),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->like('pi_flexform', $queryBuilder->createNamedParameter('%index%')),
                    $queryBuilder->expr()->like('pi_flexform', $queryBuilder->createNamedParameter('%new%')),
                ),
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
            if (\str_contains($switchableControllerActions, 'new')) {
                $plugin = 'new';
            }

            $listType = 'pwcomments_' . $plugin;

            $updateQb = $this->getQueryBuilder();
            $updateQb
                ->update(self::TABLE)
                ->set('pi_flexform', null)
                ->set('list_type', $listType)
                ->where(
                    $updateQb->expr()->eq('uid', $record['uid']),
                )
                ->executeStatement();
            ++$updatedRecords;
        }

        $this->output->writeln(\sprintf('%d records updated', $updatedRecords));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = $this->getQueryBuilder();
        $count = $queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter('pwcomments_pi1')),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->like('pi_flexform', $queryBuilder->createNamedParameter('%index%')),
                    $queryBuilder->expr()->like('pi_flexform', $queryBuilder->createNamedParameter('%new%')),
                ),
            )
            ->executeQuery()
            ->fetchOne();

        return (int) $count > 0;
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

    /**
     * Per-call QueryBuilder with only the deleted-row restriction. An upgrade
     * wizard must still see hidden/disabled rows (an editor may have hidden
     * the legacy plugin before migration), but should skip tombstones.
     */
    private function getQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder;
    }
}
