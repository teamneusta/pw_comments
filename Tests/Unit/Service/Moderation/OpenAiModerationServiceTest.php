<?php

declare(strict_types=1);

namespace T3\PwComments\Tests\Unit\Service\Moderation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use T3\PwComments\Domain\Model\Comment;
use T3\PwComments\Service\Moderation\ModerationResult;
use T3\PwComments\Service\Moderation\OpenAiModerationService;
use TYPO3\CMS\Core\Http\RequestFactory;

final class OpenAiModerationServiceTest extends TestCase
{
    private OpenAiModerationService $service;
    private MockObject $requestFactory;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->requestFactory = $this->createMock(RequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new OpenAiModerationService(
            $this->requestFactory,
            $this->logger,
            'test-api-key',
            'https://api.openai.com/v1/moderations',
            0.7
        );
    }

    public function testThrowsExceptionWhenApiKeyIsEmpty(): void
    {
        $service = new OpenAiModerationService(
            $this->requestFactory,
            $this->logger,
            '',
            'https://api.openai.com/v1/moderations',
            0.7
        );

        $comment = $this->createComment('Test message');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('OpenAI API key not configured for moderation');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenAI API key not configured for moderation');

        $service->moderateComment($comment);
    }

    public function testHandlesEmptyCommentMessage(): void
    {
        $comment = $this->createComment('');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Empty comment message, skipping AI moderation');

        $result = $this->service->moderateComment($comment);

        self::assertInstanceOf(ModerationResult::class, $result);
        self::assertFalse($result->isViolation());
        self::assertSame('Empty message', $result->getReason());
    }

    public function testHandlesWhitespaceOnlyMessage(): void
    {
        $comment = $this->createComment('   ');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Empty comment message, skipping AI moderation');

        $result = $this->service->moderateComment($comment);

        self::assertFalse($result->isViolation());
    }

    public function testSuccessfulModerationWithCleanContent(): void
    {
        $comment = $this->createComment('This is a nice comment');
        $apiResponse = [
            'results' => [
                [
                    'flagged' => false,
                    'categories' => [
                        'harassment' => false,
                        'violence' => false,
                        'sexual' => false,
                        'hate' => false
                    ],
                    'category_scores' => [
                        'harassment' => 0.1,
                        'violence' => 0.05,
                        'sexual' => 0.02,
                        'hate' => 0.03
                    ]
                ]
            ]
        ];

        $this->mockApiCall($apiResponse);

        $result = $this->service->moderateComment($comment);

        self::assertFalse($result->isViolation());
        self::assertSame([], $result->getCategories());
        self::assertSame($apiResponse['results'][0]['category_scores'], $result->getCategoryScores());
        self::assertSame('', $result->getReason());
        self::assertSame(0.1, $result->getMaxScore());
    }

    public function testSuccessfulModerationWithFlaggedContent(): void
    {
        $comment = $this->createComment('This is inappropriate content');
        $apiResponse = [
            'results' => [
                [
                    'flagged' => true,
                    'categories' => [
                        'harassment' => true,
                        'violence' => false,
                        'sexual' => false,
                        'hate' => false
                    ],
                    'category_scores' => [
                        'harassment' => 0.8,
                        'violence' => 0.1,
                        'sexual' => 0.05,
                        'hate' => 0.2
                    ]
                ]
            ]
        ];

        $this->mockApiCall($apiResponse);

        $result = $this->service->moderateComment($comment);

        self::assertTrue($result->isViolation());
        self::assertSame(['harassment'], $result->getCategories());
        self::assertSame('Content flagged for: harassment', $result->getReason());
    }

    public function testModerationWithHighScoreButNotFlagged(): void
    {
        $comment = $this->createComment('Borderline content');
        $apiResponse = [
            'results' => [
                [
                    'flagged' => false,
                    'categories' => [
                        'harassment' => false,
                        'violence' => false
                    ],
                    'category_scores' => [
                        'harassment' => 0.75,
                        'violence' => 0.1
                    ]
                ]
            ]
        ];

        $this->mockApiCall($apiResponse);

        $result = $this->service->moderateComment($comment);

        self::assertTrue($result->isViolation());
        self::assertSame('Content flagged for harassment (score: 0.75)', $result->getReason());
    }

    public static function apiErrorDataProvider(): \Generator
    {
        yield 'unauthorized' => [401, 'Invalid API key'];
        yield 'rate limited' => [429, 'Rate limit exceeded'];
        yield 'server error' => [500, 'OpenAI service temporarily unavailable'];
        yield 'bad gateway' => [502, 'OpenAI service temporarily unavailable'];
        yield 'service unavailable' => [503, 'OpenAI service temporarily unavailable'];
        yield 'gateway timeout' => [504, 'OpenAI service temporarily unavailable'];
        yield 'generic error' => [400, 'Bad Request Error'];
    }

    /**
     * @dataProvider apiErrorDataProvider
     */
    public function testHandlesApiErrors(int $statusCode, string $expectedErrorText): void
    {
        $comment = $this->createComment('Test message');
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn($statusCode);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $stream->expects($this->once())
            ->method('getContents')
            ->willReturn('Bad Request Error');

        $this->requestFactory->expects($this->once())
            ->method('request')
            ->with(
                'https://api.openai.com/v1/moderations',
                'POST',
                $this->anything()
            )
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('OpenAI moderation API error'),
                $this->anything()
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedErrorText);

        $this->service->moderateComment($comment);
    }

    public function testHandlesInvalidJsonResponse(): void
    {
        $comment = $this->createComment('Test message');
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $stream->expects($this->once())
            ->method('getContents')
            ->willReturn('invalid json');

        $this->requestFactory->expects($this->once())
            ->method('request')
            ->with(
                'https://api.openai.com/v1/moderations',
                'POST',
                $this->anything()
            )
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('OpenAI moderation API error'),
                $this->anything()
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON response from OpenAI API');

        $this->service->moderateComment($comment);
    }

    public function testHandlesUnexpectedResponseFormat(): void
    {
        $comment = $this->createComment('Test message');
        $apiResponse = ['unexpected' => 'format'];

        $this->mockApiCall($apiResponse);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('OpenAI moderation API error'),
                $this->anything()
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected response format from OpenAI API');

        $this->service->moderateComment($comment);
    }

    public function testHandlesGenericException(): void
    {
        $comment = $this->createComment('Test message');

        $this->requestFactory->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willThrowException(new \Exception('Network error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Unexpected error during AI moderation'),
                $this->anything()
            );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI moderation service unavailable');

        $this->service->moderateComment($comment);
    }

    public function testSendsCorrectApiRequest(): void
    {
        $comment = $this->createComment('Test message content');
        $expectedRequestData = ['input' => 'Test message content'];
        $expectedOptions = [
            'headers' => [
                'Authorization' => 'Bearer test-api-key',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($expectedRequestData)
        ];

        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $stream->expects($this->once())
            ->method('getContents')
            ->willReturn(json_encode([
                'results' => [
                    [
                        'flagged' => false,
                        'categories' => [],
                        'category_scores' => []
                    ]
                ]
            ]));

        $this->requestFactory->expects($this->once())
            ->method('request')
            ->with(
                'https://api.openai.com/v1/moderations',
                'POST',
                $expectedOptions
            )
            ->willReturn($response);

        $this->service->moderateComment($comment);
    }

    private function createComment(string $message): Comment
    {
        $comment = $this->createMock(Comment::class);
        $comment->expects($this->any())
            ->method('getMessage')
            ->willReturn($message);
        $comment->expects($this->any())
            ->method('getUid')
            ->willReturn(123);

        return $comment;
    }

    private function mockApiCall(array $responseData): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);
        $stream->expects($this->once())
            ->method('getContents')
            ->willReturn(json_encode($responseData));

        $this->requestFactory->expects($this->once())
            ->method('request')
            ->with(
                'https://api.openai.com/v1/moderations',
                'POST',
                $this->anything()
            )
            ->willReturn($response);
    }
}