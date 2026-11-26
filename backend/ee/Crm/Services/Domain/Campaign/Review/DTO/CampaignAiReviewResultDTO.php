<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CampaignAiReviewResultDTO extends BaseDataObject
{
    public const DECISION_APPROVED = 'APPROVED';

    public const DECISION_NEEDS_PERSON = 'NEEDS_PERSON';

    /**
     * @param  string[]  $reasons
     */
    public function __construct(
        public readonly bool $approves,
        public readonly float $confidence,
        public readonly array $reasons,
    ) {}

    /**
     * @param  string[]  $reasons
     */
    public function needingPersonBecause(array $reasons): self
    {
        return new self(false, $this->confidence, $reasons);
    }

    /**
     * @return array{decision: string, confidence: float, reasons: string[], reviewed_at: string}
     */
    public function toVerdictArray(): array
    {
        return [
            'decision' => $this->approves ? self::DECISION_APPROVED : self::DECISION_NEEDS_PERSON,
            'confidence' => $this->confidence,
            'reasons' => $this->reasons,
            'reviewed_at' => now()->toIso8601String(),
        ];
    }
}
