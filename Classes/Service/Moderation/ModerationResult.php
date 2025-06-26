<?php

declare(strict_types=1);

namespace T3\PwComments\Service\Moderation;

// TODO: Switch to readonly properties once PHP 8.2+ is required
final class ModerationResult
{
    private bool $isViolation;
    private array $categories;
    private array $categoryScores;
    private string $reason;
    private float $maxScore;

    public function __construct(
        bool $isViolation,
        array $categories = [],
        array $categoryScores = [],
        string $reason = '',
        float $maxScore = 0.0
    ) {
        $this->isViolation = $isViolation;
        $this->categories = $categories;
        $this->categoryScores = $categoryScores;
        $this->reason = $reason;
        $this->maxScore = $maxScore;
    }

    public function isViolation(): bool
    {
        return $this->isViolation;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getCategoryScores(): array
    {
        return $this->categoryScores;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getMaxScore(): float
    {
        return $this->maxScore;
    }

    public function getFormattedReason(): string
    {
        if (!empty($this->reason)) {
            return $this->reason;
        }

        if (!empty($this->categories)) {
            return 'Content flagged for: ' . implode(', ', $this->categories);
        }

        return 'AI moderation flagged this content';
    }
}