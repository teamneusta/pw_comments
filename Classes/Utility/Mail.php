<?php
namespace PwCommentsTeam\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2017 Armin Ruediger Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use PwCommentsTeam\PwComments\Domain\Model\Comment;

/**
 * This class provides some methods to build and send mails
 *
 * @package PwCommentsTeam\PwComments
 */
class Mail
{
    /**
     * @var array settings of controller
     */
    protected $settings = [];

    /**
     * @var \TYPO3\CMS\Fluid\View\TemplateView
     */
    protected $fluidTemplate = null;

    /**
     * @var \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext
     */
    protected $controllerContext = null;

    /**
     * @var string comma separated string of mail addresses
     */
    protected $receivers = '';

    /**
     * @var string
     */
    protected $templatePath = '';

    /**
     * @var string
     */
    protected $subjectLocallangKey = 'tx_pwcomments.notificationMail.subject';

    /**
     * @var bool
     */
    protected $addQueryStringToLinks = true;

    /**
     * Sets the settings of controller
     *
     * @param array $settings settings to set
     * @return void
     */
    public function setSettings(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Set the fluid template from controller
     *
     * @param \TYPO3\CMS\Fluid\View\StandaloneView $fluidTemplate the fluid template
     * @return void
     */
    public function setFluidTemplate(\TYPO3\CMS\Fluid\View\StandaloneView $fluidTemplate)
    {
        $this->fluidTemplate = $fluidTemplate;
    }

    /**
     * Set the controller context from controller
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext $controllerContext
     * @return void
     */
    public function setControllerContext(\TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext $controllerContext)
    {
        $this->controllerContext = $controllerContext;
    }

    /**
     * Creates and sends mail
     *
     * @param Comment $comment comment
     * @param string $hash validation string to add to url if comment must be moderate
     * @return bool Returns TRUE if the mail has been sent successfully
     */
    public function sendMail(Comment $comment, $hash = '')
    {
        /** @var \TYPO3\CMS\Core\Mail\MailMessage $mail */
        $mail = GeneralUtility::makeInstance('TYPO3\CMS\Core\Mail\MailMessage');

        $mail->setFrom(
            LocalizationUtility::translate(
                'tx_pwcomments.notificationMail.from.mail',
                'PwComments',
                [GeneralUtility::getHostname()]
            ),
            LocalizationUtility::translate(
                'tx_pwcomments.notificationMail.from.name',
                'PwComments'
            )
        );

        $receivers = GeneralUtility::trimExplode(',', $this->getReceivers(), true);
        $mail->setTo($receivers);
        $mail->setSubject(
            LocalizationUtility::translate(
                $this->getSubjectLocallangKey(),
                'PwComments',
                [GeneralUtility::getHostname()]
            )
        );
        $mail->addPart($this->getMailMessage($comment, $hash), $this->settings['sendMailMimeType']);
        return (bool) $mail->send();
    }

    /**
     * Gets the message for a notification mail as fluid template
     *
     * @param Comment $comment comment which triggers the mail send method
     * @param string $hash validation string to add to url if comment must be moderate
     * @return string The rendered fluid template (HTML or plain text)
     *
     * @throws \Exception
     */
    protected function getMailMessage(Comment $comment, $hash)
    {
        $mailTemplate = GeneralUtility::getFileAbsFileName($this->getTemplatePath());
        if (!file_exists($mailTemplate)) {
            throw new \Exception('Mail template (' . $mailTemplate . ') not found. ');
        }

        $this->fluidTemplate->setTemplatePathAndFilename($mailTemplate);
        $this->fluidTemplate->assignMultiple(
            [
                'hash' => $hash,
                'comment' => $comment,
                'settings' => $this->settings
            ]
        );
        return $this->fluidTemplate->render();
    }

    /**
     * Get receivers
     *
     * @return string
     */
    public function getReceivers()
    {
        return $this->receivers;
    }

    /**
     * Set receivers
     *
     * @param string $receivers
     * @return void
     */
    public function setReceivers($receivers)
    {
        $this->receivers = $receivers;
    }

    /**
     * Get template path
     *
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->templatePath;
    }

    /**
     * Set template path
     *
     * @param string $templatePath
     * @return void
     */
    public function setTemplatePath($templatePath)
    {
        $this->templatePath = $templatePath;
    }

    /**
     * Get subject locallang key
     *
     * @return string
     */
    public function getSubjectLocallangKey()
    {
        return $this->subjectLocallangKey;
    }

    /**
     * Set subject locallang key
     *
     * @param string $subjectLocallangKey
     * @return void
     */
    public function setSubjectLocallangKey($subjectLocallangKey)
    {
        $this->subjectLocallangKey = $subjectLocallangKey;
    }

    /**
     * Get add query string to links
     *
     * @return bool
     */
    public function getAddQueryStringToLinks()
    {
        return $this->addQueryStringToLinks;
    }

    /**
     * Set add query string to links
     *
     * @param bool $addQueryStringToLinks
     * @return void
     */
    public function setAddQueryStringToLinks($addQueryStringToLinks)
    {
        $this->addQueryStringToLinks = $addQueryStringToLinks;
    }
}
