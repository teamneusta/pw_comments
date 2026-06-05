<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Domain\Validator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Domain\Validator\CommentValidator;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class CommentValidatorTest extends TestCase
{
    private static string $badWordsFile;

    private ConfigurationManagerInterface $configurationManager;

    public static function setUpBeforeClass(): void
    {
        self::$badWordsFile = tempnam(sys_get_temp_dir(), 'pw_comments_bad_words_');
        file_put_contents(self::$badWordsFile, "forbidden\nblocked\n");

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

    public static function tearDownAfterClass(): void
    {
        if (file_exists(self::$badWordsFile)) {
            unlink(self::$badWordsFile);
        }
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        GeneralUtility::resetSingletonInstances([]);
    }

    #[Test]
    public function happyPathProducesNoErrors(): void
    {
        $this->stubSettings(['secondsBetweenTwoComments' => 0]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => 'Hello world',
        ]));

        self::assertFalse($result->hasErrors());
    }

    #[Test]
    public function reportsMissingPropertiesWhenNameAndMailAreEmptyAndNoFrontendUser(): void
    {
        $this->stubSettings([]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => '',
            'authorMail' => '',
            'message' => 'Hi',
        ]));

        $this->assertSingleErrorCode($result, 1299628038);
    }

    #[Test]
    public function reportsInvalidMailWhenNameIsSetButMailIsMalformed(): void
    {
        $this->stubSettings([]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'not-a-real-email',
            'message' => 'Hi',
        ]));

        $this->assertSingleErrorCode($result, 1299628371);
    }

    #[Test]
    public function reportsMissingMessageWhenMessageIsWhitespaceOnly(): void
    {
        $this->stubSettings(['secondsBetweenTwoComments' => 60]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => "   \n\t  ",
        ]));

        $this->assertSingleErrorCode($result, 1299628099);
    }

    #[Test]
    public function reportsMissingAcceptedTermsWhenRequired(): void
    {
        $this->stubSettings(['requireAcceptedTerms' => 1]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => 'Hi',
            'termsAccepted' => false,
        ]));

        $this->assertSingleErrorCode($result, 1528633964);
    }

    #[Test]
    public function reportsBadWordsInMessage(): void
    {
        $this->stubSettings([
            'useBadWordsList' => 1,
            'badWordsList' => self::$badWordsFile,
        ]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => 'This is forbidden content',
        ]));

        $this->assertSingleErrorCode($result, 1315608355);
    }

    #[Test]
    public function reportsBadWordsInAuthorName(): void
    {
        $this->stubSettings([
            'useBadWordsListOnUsername' => 1,
            'badWordsList' => self::$badWordsFile,
        ]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'forbidden user',
            'authorMail' => 'alice@example.com',
            'message' => 'clean message',
        ]));

        $this->assertSingleErrorCode($result, 1406644911);
    }

    #[Test]
    public function reportsBadWordsInAuthorMail(): void
    {
        $this->stubSettings([
            'useBadWordsListOnMailAddress' => 1,
            'badWordsList' => self::$badWordsFile,
        ]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'forbidden@example.com',
            'message' => 'clean message',
        ]));

        $this->assertSingleErrorCode($result, 1406644912);
    }

    #[Test]
    public function reportsMissingRatingWhenRatingIsEnabled(): void
    {
        $this->stubSettings(['enableRating' => 1]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => 'Hi',
            'rating' => 0,
        ]));

        $this->assertSingleErrorCode($result, 1406644913);
    }

    #[Test]
    public function reportsTimerViolationWhenLastCommentWasTooRecent(): void
    {
        $this->stubSettings(['secondsBetweenTwoComments' => 300]);

        $feUser = $this->createMock(FrontendUserAuthentication::class);
        $feUser->user = ['uid' => 42];
        $feUser
            ->method('getKey')
            ->with('ses', 'tx_pwcomments_lastComment')
            ->willReturn(time() - 10);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('frontend.user')->willReturn($feUser);

        $validator = $this->buildValidator();
        $validator->setRequest($request);

        $result = $validator->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => 'Hi',
        ]));

        $this->assertSingleErrorCode($result, 1300280476);
    }

    #[Test]
    public function happyPathSurvivesSparseSettingsArray(): void
    {
        $this->stubSettings([]);

        $result = $this->buildValidator()->validate($this->buildComment([
            'authorName' => 'Alice',
            'authorMail' => 'alice@example.com',
            'message' => 'Hi',
        ]));

        self::assertFalse($result->hasErrors());
    }

    private function buildValidator(): CommentValidator
    {
        return new CommentValidator($this->configurationManager);
    }

    private function buildComment(array $attributes): Comment
    {
        $comment = new Comment();
        $comment->setAuthorName($attributes['authorName'] ?? '');
        $comment->setAuthorMail($attributes['authorMail'] ?? '');
        $comment->setMessage($attributes['message'] ?? '');
        $comment->setTermsAccepted($attributes['termsAccepted'] ?? true);
        $comment->setRating($attributes['rating'] ?? 0);
        return $comment;
    }

    private function stubSettings(array $settings): void
    {
        // The validator reads CONFIGURATION_TYPE_SETTINGS (plain plugin settings)
        // from its injected ConfigurationManager.
        $configurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $configurationManager
            ->method('getConfiguration')
            ->with(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS)
            ->willReturn($settings);
        $this->configurationManager = $configurationManager;

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('translate')->willReturn('');
        $languageService->method('sL')->willReturn('');

        $factory = $this->createMock(LanguageServiceFactory::class);
        $factory->method('create')->willReturn($languageService);
        $factory->method('createFromUserPreferences')->willReturn($languageService);
        $factory->method('createFromSiteLanguage')->willReturn($languageService);
        GeneralUtility::addInstance(LanguageServiceFactory::class, $factory);

        $locales = $this->createMock(Locales::class);
        $locales->method('createLocaleFromRequest')->willReturn(new Locale('en'));
        $locales->method('createLocale')->willReturn(new Locale('en'));
        GeneralUtility::setSingletonInstance(Locales::class, $locales);
    }

    private function assertSingleErrorCode(\TYPO3\CMS\Extbase\Error\Result $result, int $expectedCode): void
    {
        self::assertTrue($result->hasErrors(), 'expected validation to fail');
        $errors = $result->getErrors();
        self::assertCount(1, $errors, 'expected exactly one error from the elseif chain');
        self::assertSame($expectedCode, $errors[0]->getCode());
    }
}
