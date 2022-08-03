<?php
namespace T3\PwComments\Utility;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2011-2022 Armin Vieweg <armin@v.ieweg.de>
 *  |     2015 Dennis Roemmich <dennis@roemmich.eu>
 *  |     2016-2017 Christian Wolfram <c.wolfram@chriwo.de>
 */
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use T3\PwComments\Domain\Model\Comment;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Fluid\View\TemplateView;

/**
 * This class provides some methods to build and send mails
 *
 * @package T3\PwComments
 */
class Mail
{
    /**
     * @var array settings of controller
     */
    protected $settings = [];

    /**
     * @var TemplateView|StandaloneView
     */
    protected $fluidTemplate;

    /**
     * @var ControllerContext
     */
    protected $controllerContext;

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
     * @param StandaloneView $fluidTemplate the fluid template
     * @return void
     */
    public function setFluidTemplate(StandaloneView $fluidTemplate = null)
    {
        if (!$fluidTemplate) {
            $fluidTemplate = GeneralUtility::makeInstance(StandaloneView::class);
        }
        $this->fluidTemplate = $fluidTemplate;
    }

    /**
     * Set the controller context from controller
     *
     * @param ControllerContext $controllerContext
     * @return void
     */
    public function setControllerContext(ControllerContext $controllerContext)
    {
        $this->controllerContext = $controllerContext;
    }

    /**
     * Creates and sends mail
     *
     * @param Comment $comment comment
     * @param string $hash validation string to add to url if comment must be moderate
     * @return bool Returns TRUE if the mail has been sent successfully
     * @throws \Exception
     */
    public function sendMail(Comment $comment, $hash = '')
    {
        /** @var MailMessage $mail */
        $mail = GeneralUtility::makeInstance(MailMessage::class);

        $mail->setFrom(
            $this->settings['senderAddress'] ? $this->settings['senderAddress'] : LocalizationUtility::translate(
                'tx_pwcomments.notificationMail.from.mail',
                'PwComments',
                [$this->settings['sitenameUsedInMails']
                    ? $this->settings['sitenameUsedInMails']
                    : GeneralUtility::getIndpEnv('HTTP_HOST')]
            ),
            $this->settings['senderName'] ? $this->settings['senderName'] : LocalizationUtility::translate(
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
                [$this->settings['sitenameUsedInMails']
                    ? $this->settings['sitenameUsedInMails']
                    : GeneralUtility::getIndpEnv('HTTP_HOST')]
            )
        );
        if (isset($this->settings['sendMailMimeType']) && $this->settings['sendMailMimeType'] === 'text/plain') {
            $mail->text($this->getMailMessage($comment, $hash));
        } else {
            $mail->html($this->getMailMessage($comment, $hash));
        }

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
