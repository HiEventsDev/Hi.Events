<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Message\Review;

use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Message\Review\DTO\MessageAiReviewResultDTO;
use HiEvents\Services\Infrastructure\Ai\Agents\MessageReviewAgent;
use HiEvents\Services\Infrastructure\Ai\UntrustedPromptText;
use Illuminate\Config\Repository;

class MessageAiReviewService
{
    private const SUBJECT_MAX_LENGTH = 500;

    private const MESSAGE_MAX_LENGTH = 12000;

    private const NAME_MAX_LENGTH = 200;

    public function __construct(
        private readonly Repository $config,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly UntrustedPromptText $promptText,
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->get('app.saas_mode_enabled')
            && $this->config->get('app.message_ai_review_enabled')
            && $this->config->get('ai.providers.anthropic.key');
    }

    public function review(MessageDomainObject $message, int $recipientsCount): MessageAiReviewResultDTO
    {
        if (preg_match('/<img\b|\burl\s*\(/i', $message->getMessage()) === 1) {
            return new MessageAiReviewResultDTO(false, 0.0, [
                __('The message contains images, which the automatic check can\'t read.'),
            ]);
        }

        $response = (new MessageReviewAgent)->prompt($this->buildPrompt($message, $recipientsCount));

        $confidence = (float) ($response['confidence'] ?? 0.0);
        $threshold = (float) $this->config->get('app.message_ai_review_confidence_threshold');

        return new MessageAiReviewResultDTO(
            approves: (bool) ($response['approve'] ?? false) && $confidence >= $threshold,
            confidence: $confidence,
            reasons: array_values(array_map('strval', (array) ($response['reasons'] ?? []))),
        );
    }

    private function buildPrompt(MessageDomainObject $message, int $recipientsCount): string
    {
        $event = $this->eventRepository->findFirstWhere(['id' => $message->getEventId()]);
        $organizer = $event === null ? null : $this->organizerRepository->findFirstWhere(['id' => $event->getOrganizerId()]);

        $facts = implode("\n", [
            '<held_for_review_because>'.$this->promptText->clip(implode(', ', $message->eligibilityFailureValues()), self::NAME_MAX_LENGTH).'</held_for_review_because>',
            '<event_title>'.$this->promptText->clip((string) $event?->getTitle(), self::NAME_MAX_LENGTH).'</event_title>',
            '<organizer_name>'.$this->promptText->clip((string) $organizer?->getName(), self::NAME_MAX_LENGTH).'</organizer_name>',
            '<sent_to>'.$this->promptText->clip($message->getType(), self::NAME_MAX_LENGTH).'</sent_to>',
            '<recipients>'.$recipientsCount.'</recipients>',
            '<subject>'.$this->promptText->clip($message->getSubject(), self::SUBJECT_MAX_LENGTH).'</subject>',
        ]);
        $body = $this->promptText->htmlToPlainText($message->getMessage(), self::MESSAGE_MAX_LENGTH);
        $links = implode("\n", $this->promptText->extractLinks($message->getMessage()));

        return <<<PROMPT
        <message_review>
        {$facts}
        <body>
        {$body}
        </body>
        <links>
        {$links}
        </links>
        </message_review>
        PROMPT;
    }
}
