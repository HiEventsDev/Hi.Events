<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

final class CampaignTokenCatalog
{
    public const RECIPIENT_TOKENS = ['first_name', 'last_name', 'last_event_title', 'last_event_date'];

    public const ORGANIZER_TOKENS = ['organizer_name'];

    public const EVENT_TOKENS = ['event_title', 'event_date', 'event_url'];

    private const MAX_SUGGESTION_DISTANCE = 3;

    private const QUOTE = '(?:"|&quot;|\'|&#039;)?';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [...self::RECIPIENT_TOKENS, ...self::ORGANIZER_TOKENS, ...self::EVENT_TOKENS];
    }

    /**
     * @return string[]
     */
    public static function allowed(bool $hasEvent): array
    {
        return $hasEvent ? self::all() : [...self::RECIPIENT_TOKENS, ...self::ORGANIZER_TOKENS];
    }

    public static function tagPattern(): string
    {
        return '\{\{\s*('.implode('|', self::all()).')\s*(?:\|\s*default:\s*'.self::QUOTE.'([^"{}]*?)'.self::QUOTE.'\s*)?\}\}';
    }

    /**
     * @return string[]
     */
    public static function used(string $text): array
    {
        preg_match_all('/\{\{\s*([^\s|}]*)/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return string[]
     */
    public static function unknown(string $text, bool $hasEvent): array
    {
        $allowed = self::allowed($hasEvent);

        return array_values(array_filter(self::used($text), static fn (string $token) => ! in_array($token, $allowed, true)));
    }

    public static function suggestion(string $token, bool $hasEvent): ?string
    {
        $best = null;
        $bestDistance = self::MAX_SUGGESTION_DISTANCE + 1;

        foreach (self::allowed($hasEvent) as $candidate) {
            $distance = levenshtein(strtolower($token), $candidate);

            if ($distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    public static function firstError(string $text, bool $hasEvent, bool $isTemplate = false): ?string
    {
        $token = self::unknown($text, $hasEvent)[0] ?? null;

        if ($token === null) {
            return self::firstUnsupportedTagError($text);
        }

        $placeholder = '{{ '.$token.' }}';

        if (in_array($token, self::EVENT_TOKENS, true)) {
            return $isTemplate
                ? __('Templates can\'t use event placeholders such as :placeholder. Remove it here, then add it to the campaign you start from this template.', ['placeholder' => $placeholder])
                : __(':placeholder only works when an event is linked.', ['placeholder' => $placeholder]);
        }

        $suggestion = self::suggestion($token, $hasEvent);

        return $suggestion === null
            ? __(':placeholder isn\'t a placeholder we know. Use the Insert menu to add one.', ['placeholder' => $placeholder])
            : __(':placeholder isn\'t a placeholder we know. Did you mean :suggestion?', ['placeholder' => $placeholder, 'suggestion' => '{{ '.$suggestion.' }}']);
    }

    private static function firstUnsupportedTagError(string $text): ?string
    {
        preg_match_all('/\{\{.*?\}\}/s', $text, $matches);

        foreach ($matches[0] as $tag) {
            if (! preg_match('/^'.self::tagPattern().'$/', $tag)) {
                return __(':placeholder can\'t be used. Placeholders can only have a fallback, like :example.', [
                    'placeholder' => html_entity_decode($tag, ENT_QUOTES),
                    'example' => '{{ first_name | default: "there" }}',
                ]);
            }
        }

        return null;
    }

    public static function usesAny(string $text, array $tokens): bool
    {
        return array_intersect(self::used($text), $tokens) !== [];
    }
}
