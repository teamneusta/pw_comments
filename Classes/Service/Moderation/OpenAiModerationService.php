<?php

declare(strict_types=1);

namespace T3\PwComments\Service\Moderation;

use T3\PwComments\Domain\Model\Comment;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\Channel;
use Psr\Log\LoggerInterface;

#[Channel('pw_comments')]
class OpenAiModerationService implements ModerationServiceInterface
{
    private RequestFactory $requestFactory;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $apiEndpoint;
    private float $threshold;

    public function __construct(
        RequestFactory $requestFactory,
        LoggerInterface $logger,
        string $apiKey,
        string $apiEndpoint = 'https://api.openai.com/v1/moderations',
        float $threshold = 0.7
    ) {
        $this->requestFactory = $requestFactory;
        $this->logger = $logger;
        $this->apiKey = $apiKey;
        $this->apiEndpoint = $apiEndpoint;
        $this->threshold = $threshold;
    }

    public function moderateComment(Comment $comment): ModerationResult
    {
        if (empty($this->apiKey)) {
            $this->logger->warning('OpenAI API key not configured for moderation');
            throw new \InvalidArgumentException('OpenAI API key not configured for moderation');
        }

        $message = trim($comment->getMessage());
        if (empty($message)) {
            $this->logger->info('Empty comment message, skipping AI moderation');
            return new ModerationResult(false, [], [], 'Empty message');
        }

        try {
            $response = $this->callOpenAiApi($message);
            return $this->parseResponse($response);
        } catch (\RuntimeException $e) {
            $this->logger->error('OpenAI moderation API error: ' . $e->getMessage(), [
                'comment_uid' => $comment->getUid(),
                'comment_preview' => substr($message, 0, 100),
                'exception' => $e
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during AI moderation: ' . $e->getMessage(), [
                'comment_uid' => $comment->getUid(),
                'comment_preview' => substr($message, 0, 100),
                'exception' => $e
            ]);
            throw new \RuntimeException('AI moderation service unavailable', 0, $e);
        }
    }

    private function callOpenAiApi(string $content): array
    {
        $requestData = [
            'input' => $content
        ];

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($requestData)
        ];

        $response = $this->requestFactory->request($this->apiEndpoint, 'POST', $options);
        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();

        if ($statusCode !== 200) {
            $errorMessage = sprintf('OpenAI API returned status code %d', $statusCode);
            
            // Handle specific error cases
            throw match ($statusCode) {
                401 => new \RuntimeException($errorMessage . ': Invalid API key'),
                429 => new \RuntimeException($errorMessage . ': Rate limit exceeded'),
                500, 502, 503, 504 => new \RuntimeException($errorMessage . ': OpenAI service temporarily unavailable'),
                default => new \RuntimeException($errorMessage . ': ' . $responseBody),
            };
        }

        $responseData = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from OpenAI API: ' . json_last_error_msg());
        }

        return $responseData;
    }

    private function parseResponse(array $response): ModerationResult
    {
        if (!isset($response['results'][0])) {
            throw new \RuntimeException('Unexpected response format from OpenAI API');
        }

        $result = $response['results'][0];
        $flagged = $result['flagged'] ?? false;
        $categories = $result['categories'] ?? [];
        $categoryScores = $result['category_scores'] ?? [];

        $flaggedCategories = [];
        $maxScore = 0.0;
        $maxCategory = '';

        foreach ($categories as $category => $isFlagged) {
            if ($isFlagged) {
                $flaggedCategories[] = $category;
            }
        }

        foreach ($categoryScores as $category => $score) {
            if ($score > $maxScore) {
                $maxScore = $score;
                $maxCategory = $category;
            }
        }

        $isViolation = $flagged || $maxScore >= $this->threshold;
        $reason = '';

        if ($isViolation) {
            if (!empty($flaggedCategories)) {
                $reason = 'Content flagged for: ' . implode(', ', $flaggedCategories);
            } else {
                $reason = sprintf('Content flagged for %s (score: %.2f)', $maxCategory, $maxScore);
            }
        }

        return new ModerationResult(
            $isViolation,
            $flaggedCategories,
            $categoryScores,
            $reason,
            $maxScore
        );
    }
}