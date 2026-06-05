<?php

declare(strict_types=1);

namespace T3\PwComments\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

#[UpgradeWizard('t3PwCommentsCTypeMigration')]
final class T3PwCommentsCTypeMigration extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'Migrate "T3 PwComments" plugins to content elements.';
    }

    public function getDescription(): string
    {
        return 'The "T3 PwComments" plugins are now registered as content element. Update migrates existing records and backend user permissions.';
    }

    /**
     * This must return an array containing the "list_type" to "CType" mapping
     *
     *  Example:
     *
     *  [
     *      'pi_plugin1' => 'pi_plugin1',
     *      'pi_plugin2' => 'new_content_element',
     *  ]
     *
     * @return array<string, string>
     */
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            'pwcomments_show' => 'pwcomments_show',
            'pwcomments_new' => 'pwcomments_new',
            'pwcomments_pi2' => 'pwcomments_pi2',
        ];
    }
}
