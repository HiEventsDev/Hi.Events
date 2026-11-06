<?php

namespace HiEvents\Mcp\Tools\Support;

use HiEvents\DomainObjects\Enums\WidgetType;

class WidgetEmbedCode
{
    private const HOST_KEYS = [
        WidgetType::TICKETS->value => ['autoresize'],
        WidgetType::BUTTON->value => ['button_text', 'secondary_color', 'secondary_text_color', 'radius'],
        WidgetType::LINEUP->value => ['autoresize'],
        WidgetType::SCHEDULE->value => ['autoresize'],
        WidgetType::EVENTS->value => ['autoresize'],
    ];

    public static function forWidget(array $widget): string
    {
        $type = $widget['type'];

        $attributes = $widget['event_id'] !== null
            ? [['data-hievents-id', (string) $widget['event_id']]]
            : [['data-hievents-organizer-id', (string) $widget['organizer_id']]];

        if ($type !== WidgetType::TICKETS->value) {
            $attributes[] = ['data-hievents-widget-type', strtolower($type)];
        }

        $attributes[] = ['data-hievents-widget', $widget['short_id']];

        foreach (self::HOST_KEYS[$type] ?? [] as $key) {
            $value = $widget['settings'][$key] ?? null;

            if (self::isEmpty($key, $value)) {
                continue;
            }

            $attributes[] = ['data-hievents-'.str_replace('_', '-', $key), self::attributeValue($value)];
        }

        $lines = array_map(
            static fn (array $attribute): string => '  '.$attribute[0].'="'.self::escape($attribute[1]).'"',
            $attributes,
        );

        return '<div class="hievents-widget"'."\n".implode("\n", $lines)."\n".'></div>'."\n"
            .'<script async src="'.rtrim((string) config('app.frontend_url'), '/').'/widget.js"></script>';
    }

    private static function isEmpty(string $key, mixed $value): bool
    {
        return match (true) {
            $value === null, $value === '', $value === [] => true,
            $key === 'autoresize' => $value === true,
            is_bool($value) => $value === false,
            default => false,
        };
    }

    private static function attributeValue(mixed $value): string
    {
        return match (true) {
            is_array($value) => implode(',', $value),
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    private static function escape(string $value): string
    {
        return str_replace(['&', '"', '<'], ['&amp;', '&quot;', '&lt;'], $value);
    }
}
