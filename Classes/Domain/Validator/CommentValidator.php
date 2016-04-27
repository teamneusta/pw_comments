<?php
namespace PwCommentsTeam\PwComments\Domain\Validator;

/*  | This extension is part of the TYPO3 project. The TYPO3 project is
 *  | free software and is licensed under GNU General Public License.
 *  |
 *  | (c) 2011-2015 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 */
use PwCommentsTeam\PwComments\Domain\Model\Comment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is a domain validator of comment model for attribute
 * comprehensive validation. It checks that at least one of the required fields
 * has been filled.
 *
 * @package PwCommentsTeam\PwComments
 */
class CommentValidator extends \TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator
{
    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * @var \PwCommentsTeam\PwComments\Utility\Settings
     * @inject
     */
    protected $settingsUtility;

    /**
     * @var array Settings defined in typoscript of pw_comments
     */
    protected $settings = [];

    /**
     * Initial function to validate
     *
     * @param Comment $comment Comment model to validate
     * @return bool
     */
    public function isValid($comment)
    {
        $this->settings = $this->getExtensionSettings();

        $errorNumber = null;
        $errorArguments = null;

        if (!$this->anyPropertyIsSet($comment)) {
            $errorNumber = 1299628038;
        } elseif (!$this->mailIsValid($comment)) {
            $errorNumber = 1299628371;
        } elseif (!$this->messageIsSet($comment)) {
            $errorNumber = 1299628099;
            $errorArguments = [$this->settings['secondsBetweenTwoComments']];
        } elseif ($this->settings['useBadWordsList'] && !$this->checkTextForBadWords($comment->getMessage())) {
            $errorNumber = 1315608355;
        } elseif ($this->settings['useBadWordsListOnUsername']
            && !$this->checkTextForBadWords($comment->getAuthorName())) {
            $errorNumber = 1406644911;
        } elseif ($this->settings['useBadWordsListOnMailAddress']
            && !$this->checkTextForBadWords($comment->getAuthorMail())) {
            $errorNumber = 1406644912;
        } elseif (!$this->lastCommentRespectsTimer($comment)) {
            $errorNumber = 1300280476;
            $errorArguments = [$this->settings['secondsBetweenTwoComments']];
        }

        if ($errorNumber !== null) {
            $errorMessage = \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate(
                'tx_pwcomments.validation_error.' . $errorNumber, 'PwComments', $errorArguments
            );
            $this->addError($errorMessage, $errorNumber);
        }
        return $errorNumber === null;
    }

    /**
     * Validator to check that any property has been set in comment
     *
     * @param Comment $comment Comment model to validate
     * @return bool
     */
    protected function anyPropertyIsSet(Comment $comment)
    {
        return ($GLOBALS['TSFE']->fe_user->user['uid']) || ($comment->getAuthorName() !== '' && $comment->getAuthorMail() !== '');
    }

    /**
     * Validator to check that mail is valid
     *
     * @param Comment $comment Comment model to validate
     * @return bool
     */
    protected function mailIsValid(Comment $comment)
    {
        return $GLOBALS['TSFE']->fe_user->user['uid']
            || (is_string($comment->getAuthorMail()) && GeneralUtility::validEmail($comment->getAuthorMail()));
    }

    /**
     * Validator to check that message has been set
     *
     * @param Comment $comment Comment model to validate
     * @return bool
     */
    protected function messageIsSet(Comment $comment)
    {
        return trim($comment->getMessage());
    }

    /**
     * Check the time between last two comments of current user (using its session)
     *
     * @return bool
     */
    protected function lastCommentRespectsTimer()
    {
        if (!$GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_lastComment')) {
            return true;
        }
        $difference = intval(time() - $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_pwcomments_lastComment'));
        return $difference > $this->settings['secondsBetweenTwoComments'];
    }

    /**
     * Check for badwords in comment message
     *
     * @param string $textToCheck text to check for
     * @return bool Returns TRUE if message has no badwords. Otherwise returns FALSE.
     */
    protected function checkTextForBadWords($textToCheck)
    {
        $badWordsListPath = GeneralUtility::getFileAbsFileName($this->settings['badWordsList']);
        if (empty($textToCheck) || !file_exists($badWordsListPath)) {
            // Skip this validation, if bad word list is missing or textToCheck is empty
            return true;
        }

        $badWordsRegExp = '';
        foreach (file($badWordsListPath) as $badWord) {
            $badWordsRegExp .= trim($badWord) . '|';
        }
        $badWordsRegExp = '/' . substr($badWordsRegExp, 0, -1) . '/i';
        $commentMessage = '-> ' . $textToCheck . ' <-';
        return (bool) !preg_match($badWordsRegExp, $commentMessage);
    }

    /**
     * Returns the rendered settings of this extension
     *
     * @return array rendered typoscript settings
     */
    protected function getExtensionSettings()
    {
        $fullTyposcript = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );
        $extensionTyposcript = $fullTyposcript['plugin.']['tx_pwcomments.']['settings.'];
        return $this->settingsUtility->renderConfigurationArray($extensionTyposcript);
    }
}
