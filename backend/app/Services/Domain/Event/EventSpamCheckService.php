<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\Services\Domain\Event\DTO\EventSpamCheckResultDTO;
use HiEvents\Services\Infrastructure\Ai\Agents\EventSpamDetectionAgent;
use Illuminate\Config\Repository;

class EventSpamCheckService
{
    private const TITLE_MAX_LENGTH = 500;

    private const DESCRIPTION_MAX_LENGTH = 4000;

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->get('app.saas_mode_enabled')
            && $this->config->get('app.event_spam_check_enabled')
            && $this->config->get('ai.providers.anthropic.key');
    }

    public function checkContent(?string $title, ?string $description): EventSpamCheckResultDTO
    {
        $response = (new EventSpamDetectionAgent)->prompt($this->buildPrompt($title, $description));

        $confidence = (float) ($response['confidence'] ?? 0.0);
        $threshold = (float) $this->config->get('app.event_spam_check_confidence_threshold');

        return new EventSpamCheckResultDTO(
            isSpam: (bool) ($response['is_spam'] ?? false) && $confidence >= $threshold,
            confidence: $confidence,
            reasons: array_values(array_map('strval', (array) ($response['reasons'] ?? []))),
            model: EventSpamDetectionAgent::MODEL,
        );
    }

    public function hashContent(?string $title, ?string $description): string
    {
        return hash('sha256', ($title ?? '')."\n".($description ?? ''));
    }

    private function buildPrompt(?string $title, ?string $description): string
    {
        $title = mb_substr(trim($title ?? ''), 0, self::TITLE_MAX_LENGTH);
        $description = mb_substr(trim(strip_tags($description ?? '')), 0, self::DESCRIPTION_MAX_LENGTH);

        return <<<PROMPT
        <event_content>
        <title>{$title}</title>
        <description>{$description}</description>
        </event_content>
        PROMPT;
    }
}
