<?php

namespace HiEvents\Services\Domain\Event;

use HiEvents\Services\Domain\Event\DTO\EventSpamCheckContentDTO;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckResultDTO;
use HiEvents\Services\Infrastructure\Ai\Agents\EventSpamDetectionAgent;
use Illuminate\Config\Repository;

class EventSpamCheckService
{
    private const TITLE_MAX_LENGTH = 500;

    private const DESCRIPTION_MAX_LENGTH = 4000;

    private const SUPPLEMENTARY_MAX_LENGTH = 4000;

    private const SUPPLEMENTARY_ITEM_MAX_LENGTH = 500;

    private const LABEL_MAX_LENGTH = 100;

    private const LINK_MAX_LENGTH = 300;

    private const MAX_LINKS = 30;

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->get('app.saas_mode_enabled')
            && $this->config->get('app.event_spam_check_enabled')
            && $this->config->get('ai.providers.anthropic.key');
    }

    public function checkContent(EventSpamCheckContentDTO $content): EventSpamCheckResultDTO
    {
        $response = (new EventSpamDetectionAgent)->prompt($this->buildPrompt($content));

        $confidence = (float) ($response['confidence'] ?? 0.0);
        $threshold = (float) $this->config->get('app.event_spam_check_confidence_threshold');

        return new EventSpamCheckResultDTO(
            isSpam: (bool) ($response['is_spam'] ?? false) && $confidence >= $threshold,
            confidence: $confidence,
            reasons: array_values(array_map('strval', (array) ($response['reasons'] ?? []))),
            model: EventSpamDetectionAgent::MODEL,
        );
    }

    public function hashContent(EventSpamCheckContentDTO $content): string
    {
        return hash('sha256', implode("\n", [
            $content->title ?? '',
            $content->description ?? '',
            ...array_map(
                static fn (string $label, string $value): string => $label.':'.$value,
                array_keys($content->supplementaryContent),
                array_values($content->supplementaryContent),
            ),
        ]));
    }

    private function buildPrompt(EventSpamCheckContentDTO $content): string
    {
        $title = $this->toPlainText($content->title, self::TITLE_MAX_LENGTH);
        $description = $this->toPlainText($content->description, self::DESCRIPTION_MAX_LENGTH);
        $supplementary = $this->buildSupplementary($content);
        $links = implode("\n", $this->extractLinks($content));

        return <<<PROMPT
        <event_content>
        <title>{$title}</title>
        <description>{$description}</description>
        <additional_content>
        {$supplementary}
        </additional_content>
        <links>
        {$links}
        </links>
        </event_content>
        PROMPT;
    }

    private function buildSupplementary(EventSpamCheckContentDTO $content): string
    {
        $lines = [];
        $remaining = self::SUPPLEMENTARY_MAX_LENGTH;

        foreach ($content->supplementaryContent as $label => $value) {
            if ($remaining <= 0) {
                break;
            }

            $text = $this->toPlainText($value, min(self::SUPPLEMENTARY_ITEM_MAX_LENGTH, $remaining));

            if ($text === '') {
                continue;
            }

            $remaining -= mb_strlen($text);
            $lines[] = $this->toPlainText($label, self::LABEL_MAX_LENGTH).': '.$text;
        }

        return implode("\n", $lines);
    }

    private function toPlainText(?string $html, int $maxLength): string
    {
        $withInlineUrls = preg_replace_callback(
            '/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            fn (array $anchor): string => strip_tags($anchor[2]).' ('.$this->sanitiseUrl($anchor[1]).')',
            $html ?? '',
        );

        return mb_substr(trim(strip_tags($withInlineUrls ?? $html ?? '')), 0, $maxLength);
    }

    /**
     * @return string[]
     */
    private function extractLinks(EventSpamCheckContentDTO $content): array
    {
        $links = [];

        foreach ($content->allHtml() as $html) {
            preg_match_all('/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $html, $matches);

            foreach ($matches[1] as $url) {
                $links[] = $this->sanitiseUrl($url);
            }
        }

        return array_slice(array_values(array_unique(array_filter($links))), 0, self::MAX_LINKS);
    }

    private function sanitiseUrl(string $url): string
    {
        return mb_substr(trim(str_replace(['<', '>'], '', $url)), 0, self::LINK_MAX_LENGTH);
    }
}
