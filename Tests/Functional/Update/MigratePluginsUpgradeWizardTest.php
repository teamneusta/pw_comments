<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Functional\Update;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\NullOutput;
use T3\PwComments\Update\MigratePluginsUpgradeWizard;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test for {@see MigratePluginsUpgradeWizard}.
 *
 * Regression for issue #36 / #52: the wizard previously held a single,
 * DI-injected QueryBuilder across its select/update calls and reset it via
 * the removed `resetQueryParts()` API, which crashed `upgrade:run`. The
 * wizard now must build a fresh QueryBuilder per query and rewrite legacy
 * `pwcomments_pi1` rows to the split `pwcomments_show`/`pwcomments_new`
 * plugins (and null out the flexform that drove the old switchable actions).
 */
final class MigratePluginsUpgradeWizardTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
        'frontend',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/pw_comments',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // tt_content.list_type was removed in TYPO3 v14. The wizard targets
        // pre-v7 records that still carry list_type='pwcomments_pi1' and a
        // flexform with switchableControllerActions — both gone on v14, where
        // T3PwCommentsCTypeMigration supersedes this wizard.
        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14) {
            self::markTestSkipped('MigratePluginsUpgradeWizard is only meaningful on TYPO3 v13 (tt_content.list_type removed in v14).');
        }

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Database/tt_content_pwcomments_legacy.csv');
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenLegacyRowsExist(): void
    {
        self::assertTrue($this->buildWizard()->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesLegacyRowsToSplitPlugins(): void
    {
        $wizard = $this->buildWizard();

        self::assertTrue($wizard->executeUpdate());

        $indexRow = $this->fetchRow(10);
        self::assertSame('pwcomments_show', $indexRow['list_type']);
        self::assertNull($indexRow['pi_flexform']);

        $newRow = $this->fetchRow(11);
        self::assertSame('pwcomments_new', $newRow['list_type']);
        self::assertNull($newRow['pi_flexform']);

        $untouched = $this->fetchRow(12);
        self::assertSame('text', $untouched['CType']);
    }

    #[Test]
    public function updateNecessaryReturnsFalseAfterMigration(): void
    {
        $wizard = $this->buildWizard();
        $wizard->executeUpdate();

        self::assertFalse($this->buildWizard()->updateNecessary());
    }

    private function buildWizard(): MigratePluginsUpgradeWizard
    {
        $wizard = $this->get(MigratePluginsUpgradeWizard::class);
        $wizard->setOutput(new NullOutput());

        return $wizard;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRow(int $uid): array
    {
        $qb = $this->getConnectionPool()->getQueryBuilderForTable('tt_content');
        $qb->getRestrictions()->removeAll();

        $row = $qb
            ->select('uid', 'CType', 'list_type', 'pi_flexform')
            ->from('tt_content')
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row, sprintf('Expected tt_content row uid=%d to exist.', $uid));
        return $row;
    }
}
