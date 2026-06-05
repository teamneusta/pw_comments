<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Utility\Mail;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\View\TemplatePaths;

final class MailTest extends TestCase
{
    private const TEMPLATE_REL = 'pw_comments_mail_template.html';
    private const TEMPLATE_BODY = '<html>{settings.greet}</html>';
    private const HTTP_HOST = 'unit.example.test';
    private const FALLBACK_FROM_MAIL = 'fallback@example.test';
    private const FALLBACK_FROM_NAME = 'Fallback Sender';

    private string $templateAbs = '';

    public static function setUpBeforeClass(): void
    {
        $tmp = sys_get_temp_dir();
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            $tmp,
            $tmp,
            $tmp,
            $tmp,
            $tmp . '/_dummy_script.php',
            'UNIX',
        );
    }

    protected function setUp(): void
    {
        $this->templateAbs = Environment::getPublicPath() . '/' . self::TEMPLATE_REL;
        file_put_contents($this->templateAbs, self::TEMPLATE_BODY);

        $languageService = $this->createMock(LanguageService::class);
        $languageService
            ->method('translate')
            ->willReturnCallback(function (string $id, string $domain, array $arguments = []) {
                if (str_contains($id, 'from.mail')) {
                    return self::FALLBACK_FROM_MAIL;
                }
                if (str_contains($id, 'from.name')) {
                    return self::FALLBACK_FROM_NAME;
                }
                return $id . (empty($arguments) ? '' : ':' . implode(',', $arguments));
            });

        $factory = $this->createMock(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromUserPreferences')->willReturn($languageService);
        $factory->method('createFromSiteLanguage')->willReturn($languageService);

        $container = new class ($factory) implements ContainerInterface {
            public function __construct(private readonly LanguageServiceFactory $factory) {}

            public function has(string $id): bool
            {
                return $id === LanguageServiceFactory::class;
            }

            public function get(string $id): object
            {
                return $this->factory;
            }
        };
        GeneralUtility::setContainer($container);

        $locales = $this->createMock(Locales::class);
        $locales->method('createLocaleFromRequest')->willReturn(new Locale('en'));
        $locales->method('createLocale')->willReturn(new Locale('en'));
        GeneralUtility::setSingletonInstance(Locales::class, $locales);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->templateAbs)) {
            unlink($this->templateAbs);
        }
        GeneralUtility::purgeInstances();
        GeneralUtility::resetSingletonInstances([]);
    }

    #[Test]
    public function defaultsMatchDocumentedValues(): void
    {
        $mail = new Mail($this->createMock(MailerInterface::class));

        self::assertSame('tx_pwcomments.notificationMail.subject', $mail->getSubjectLocallangKey());
        self::assertTrue($mail->getAddQueryStringToLinks());
    }

    #[Test]
    public function sendMailUsesExplicitSenderAddressAndNameFromSettings(): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mail = $this->buildMailForSending([
            'senderAddress' => 'sender@example.com',
            'senderName' => 'Configured Sender',
        ]);

        $mail->sendMail($this->newComment(), 'h');

        self::assertCount(1, $mailMessage->getFrom());
        self::assertSame('sender@example.com', $mailMessage->getFrom()[0]->getAddress());
        self::assertSame('Configured Sender', $mailMessage->getFrom()[0]->getName());
    }

    public static function senderFallbackCases(): array
    {
        return [
            'senderAddress missing falls back to translated from.mail' => [
                ['senderName' => 'Configured Sender'],
                self::FALLBACK_FROM_MAIL,
                'Configured Sender',
            ],
            'senderName missing falls back to translated from.name' => [
                ['senderAddress' => 'sender@example.com'],
                'sender@example.com',
                self::FALLBACK_FROM_NAME,
            ],
        ];
    }

    #[Test]
    #[DataProvider('senderFallbackCases')]
    public function sendMailFallsBackToLocalizationUtilityWhenSenderFieldIsEmpty(
        array $settings,
        string $expectedAddress,
        string $expectedName,
    ): void {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mail = $this->buildMailForSending($settings);

        $mail->sendMail($this->newComment(), 'h');

        self::assertSame($expectedAddress, $mailMessage->getFrom()[0]->getAddress());
        self::assertSame($expectedName, $mailMessage->getFrom()[0]->getName());
    }

    #[Test]
    public function sendMailExplodesReceiversIntoSetToAndTrimsWhitespace(): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mail = $this->buildMailForSending(
            ['senderAddress' => 'sender@example.com', 'senderName' => 'X'],
            'one@example.com,  two@example.com  ,three@example.com,',
        );

        $mail->sendMail($this->newComment(), 'h');

        $addresses = array_map(static fn($a) => $a->getAddress(), $mailMessage->getTo());
        self::assertSame(['one@example.com', 'two@example.com', 'three@example.com'], $addresses);
    }

    public static function bodyMimeTypeCases(): array
    {
        return [
            'text/plain mime uses text body and leaves html empty' => [
                'text/plain', false,
            ],
            'default mime uses html body and leaves text empty' => [
                'text/html', true,
            ],
        ];
    }

    #[Test]
    #[DataProvider('bodyMimeTypeCases')]
    public function sendMailRoutesBodyAccordingToMimeTypeSetting(string $mimeType, bool $expectHtml): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mail = $this->buildMailForSending([
            'senderAddress' => 'sender@example.com',
            'senderName' => 'X',
            'sendMailMimeType' => $mimeType,
        ]);

        $mail->sendMail($this->newComment(), 'h');

        if ($expectHtml) {
            self::assertNotNull($mailMessage->getHtmlBody());
            self::assertNull($mailMessage->getTextBody());
        } else {
            self::assertNotNull($mailMessage->getTextBody());
            self::assertNull($mailMessage->getHtmlBody());
        }
    }

    public static function subjectHostCases(): array
    {
        return [
            'subject uses sitenameUsedInMails when set' => [
                'My Site',
                'My Site',
            ],
            'subject falls back to HTTP_HOST when sitenameUsedInMails empty' => [
                '',
                self::HTTP_HOST,
            ],
        ];
    }

    #[Test]
    #[DataProvider('subjectHostCases')]
    public function sendMailUsesTranslatedSubjectWithSitenameOrHostFallback(
        string $sitename,
        string $expectedSitenameInSubject,
    ): void {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mail = $this->buildMailForSending([
            'senderAddress' => 'sender@example.com',
            'senderName' => 'X',
            'sitenameUsedInMails' => $sitename,
        ]);

        $mail->sendMail($this->newComment(), 'h');

        self::assertSame(
            'tx_pwcomments.notificationMail.subject:' . $expectedSitenameInSubject,
            $mailMessage->getSubject(),
        );
    }

    #[Test]
    public function sendMailInvokesMailerSendOnce(): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with($mailMessage);

        $mail = $this->buildMailForSending(
            ['senderAddress' => 'sender@example.com', 'senderName' => 'X'],
            'a@example.com',
            $mailer,
        );

        $mail->sendMail($this->newComment(), 'h');
    }

    #[Test]
    public function sendMailAssignsCommentHashAndSettingsToView(): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $captured = null;
        $view = $this->createFluidViewCapturing($captured);
        $settings = [
            'senderAddress' => 'sender@example.com',
            'senderName' => 'X',
            'greet' => 'hi',
        ];
        $comment = $this->newComment();
        $mail = $this->buildMailForSending($settings, view: $view);

        $mail->sendMail($comment, 'expected-hash');

        self::assertSame('expected-hash', $captured['hash'] ?? null);
        self::assertSame($comment, $captured['comment'] ?? null);
        self::assertSame($settings, $captured['settings'] ?? null);
    }

    #[Test]
    public function sendMailDegradesGracefullyWhenRequestLacksNormalizedParamsAttribute(): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        // Request without the normalizedParams attribute: host cannot be derived.
        // sitenameUsedInMails is also empty, so the vsprintf-style sitename arg
        // is rendered as an empty string in the subject. No warnings/notices.
        $mail = $this->buildMailForSending(
            ['senderAddress' => 'sender@example.com', 'senderName' => 'X'],
            request: new ServerRequest(),
        );

        $mail->sendMail($this->newComment(), 'h');

        self::assertSame(
            'tx_pwcomments.notificationMail.subject:',
            $mailMessage->getSubject(),
        );
    }

    #[Test]
    public function sendMailThrowsWhenTemplateFileDoesNotExist(): void
    {
        $mailMessage = new MailMessage();
        GeneralUtility::addInstance(MailMessage::class, $mailMessage);

        $mail = $this->buildMailForSending(
            ['senderAddress' => 'sender@example.com', 'senderName' => 'X'],
            templatePath: 'does-not-exist-' . uniqid() . '.html',
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(1394328652);

        $mail->sendMail($this->newComment(), 'h');
    }

    private function buildMailForSending(
        array $settings,
        string $receivers = 'a@example.com',
        ?MailerInterface $mailer = null,
        ?FluidViewAdapter $view = null,
        ?string $templatePath = null,
        ?ServerRequestInterface $request = null,
    ): Mail {
        $mail = new Mail($mailer ?? $this->createMock(MailerInterface::class));
        $mail->setSettings($settings);
        $mail->setReceivers($receivers);
        $mail->setTemplatePath($templatePath ?? self::TEMPLATE_REL);
        $mail->setView($view ?? $this->createDefaultView());
        $mail->setRequest($request ?? $this->buildRequestWithHost(self::HTTP_HOST));
        return $mail;
    }

    private function buildRequestWithHost(string $host): ServerRequestInterface
    {
        return (new ServerRequest())->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromServerParams(['HTTP_HOST' => $host], []),
        );
    }

    private function createDefaultView(): FluidViewAdapter
    {
        $captured = null;
        return $this->createFluidViewCapturing($captured);
    }

    private function createFluidViewCapturing(?array &$captured): FluidViewAdapter
    {
        $templatePaths = $this->createMock(TemplatePaths::class);
        $renderingContext = $this->createMock(RenderingContextInterface::class);
        $renderingContext->method('getTemplatePaths')->willReturn($templatePaths);
        $renderingContext->method('getVariableProvider')->willReturn(new StandardVariableProvider());

        $view = $this->createMock(FluidViewAdapter::class);
        $view->method('getRenderingContext')->willReturn($renderingContext);
        $view
            ->method('assignMultiple')
            ->willReturnCallback(function (array $vars) use (&$captured, $view) {
                $captured = $vars;
                return $view;
            });
        $view->method('render')->willReturn('rendered');
        return $view;
    }

    private function newComment(): Comment
    {
        $comment = new Comment();
        $comment->setAuthorName('Author');
        $comment->setAuthorMail('author@example.com');
        $comment->setMessage('Body');
        return $comment;
    }
}
