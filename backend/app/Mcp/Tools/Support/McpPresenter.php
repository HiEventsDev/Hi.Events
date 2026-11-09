<?php

namespace HiEvents\Mcp\Tools\Support;

use Carbon\CarbonImmutable;
use HiEvents\Helper\Url;
use Illuminate\Support\Arr;

class McpPresenter
{
    public const LOCAL_DATE_FORMAT = 'Y-m-d H:i';

    public const MAX_DAILY_ROWS = 370;

    public const LOCAL_DATE_DESCRIPTION = 'Local date and time in the event timezone, formatted YYYY-MM-DD HH:MM.';

    public static function localDate(?string $utcDate, string $timezone): ?string
    {
        if ($utcDate === null || $utcDate === '') {
            return null;
        }

        return CarbonImmutable::parse($utcDate, 'UTC')->setTimezone($timezone)->toIso8601String();
    }

    public static function localInputDate(?string $utcDate, string $timezone): ?string
    {
        if ($utcDate === null || $utcDate === '') {
            return null;
        }

        return CarbonImmutable::parse($utcDate, 'UTC')->setTimezone($timezone)->format(self::LOCAL_DATE_FORMAT);
    }

    public static function organizer(array $organizer): array
    {
        return [
            'id' => $organizer['id'],
            'name' => $organizer['name'],
            'email' => $organizer['email'],
            'timezone' => $organizer['timezone'],
            'currency' => $organizer['currency'],
            'status' => $organizer['status'],
        ];
    }

    public static function eventSummary(array $event): array
    {
        return [
            'id' => $event['id'],
            'title' => $event['title'],
            'status' => $event['status'],
            'type' => $event['type'],
            'lifecycle_status' => $event['lifecycle_status'] ?? null,
            'organizer_id' => $event['organizer_id'],
            'timezone' => $event['timezone'],
            'currency' => $event['currency'],
            'category' => $event['category'] ?? null,
            'next_date' => self::localDate($event['next_occurrence_start_date'] ?? null, $event['timezone']),
            'links' => self::eventLinks($event),
        ];
    }

    public static function eventLinks(array $event): array
    {
        return [
            'dashboard' => sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_DASHBOARD), $event['id']),
            'public_page' => sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_HOMEPAGE), $event['id'], $event['slug']),
            'preview' => sprintf(Url::getFrontEndUrlFromConfig(Url::EVENT_PREVIEW), $event['id']),
        ];
    }

    public static function eventDate(array $occurrence, string $timezone): array
    {
        return [
            'id' => $occurrence['id'],
            'start_date' => self::localDate($occurrence['start_date'], $timezone),
            'end_date' => self::localDate($occurrence['end_date'] ?? null, $timezone),
            'status' => $occurrence['status'],
            'label' => $occurrence['label'] ?? null,
            'capacity' => $occurrence['capacity'] ?? null,
            'available_capacity' => $occurrence['available_capacity'] ?? null,
        ];
    }

    public static function venue(?array $eventLocation): ?array
    {
        if ($eventLocation === null) {
            return null;
        }

        return [
            'type' => $eventLocation['type'],
            'venue_id' => $eventLocation['location_id'] ?? null,
            'name' => $eventLocation['location']['name'] ?? null,
            'address' => $eventLocation['location']['structured_address'] ?? null,
            'online_details' => $eventLocation['online_event_connection_details'] ?? null,
        ];
    }

    public static function location(array $location): array
    {
        return [
            'id' => $location['id'],
            'name' => $location['name'] ?? null,
            'address' => $location['structured_address'] ?? null,
        ];
    }

    public static function ticket(array $product, string $timezone): array
    {
        return [
            'id' => $product['id'],
            'title' => $product['title'],
            'type' => $product['type'],
            'product_type' => $product['product_type'],
            'description' => $product['description'] ?? null,
            'is_hidden' => $product['is_hidden'] ?? false,
            'is_addon_only' => (bool) ($product['is_addon_only'] ?? false),
            'addon_ids' => $product['addon_product_ids'] ?? [],
            'min_per_order' => $product['min_per_order'] ?? null,
            'max_per_order' => $product['max_per_order'] ?? null,
            'sale_start_date' => self::localDate($product['sale_start_date'] ?? null, $timezone),
            'sale_end_date' => self::localDate($product['sale_end_date'] ?? null, $timezone),
            'prices' => collect($product['prices'] ?? [])
                ->map(static fn (array $price): array => [
                    'id' => $price['id'],
                    'label' => $price['label'] ?? null,
                    'price' => $price['price'],
                    'quantity_available' => $price['initial_quantity_available'] ?? null,
                    'quantity_sold' => $price['quantity_sold'] ?? null,
                    'is_hidden' => $price['is_hidden'] ?? false,
                    'sale_start_date' => self::localDate($price['sale_start_date'] ?? null, $timezone),
                    'sale_end_date' => self::localDate($price['sale_end_date'] ?? null, $timezone),
                ])
                ->values()
                ->all(),
        ];
    }

    public static function sharedCapacity(array $assignment): array
    {
        return [
            'id' => $assignment['id'],
            'name' => $assignment['name'],
            'capacity' => $assignment['capacity'] ?? null,
            'used_capacity' => $assignment['used_capacity'] ?? 0,
            'status' => $assignment['status'],
            'tickets' => collect($assignment['products'] ?? [])
                ->map(static fn (array $product): array => ['id' => $product['id'], 'title' => $product['title']])
                ->values()
                ->all(),
        ];
    }

    public static function lineupEntry(array $entry): array
    {
        return [
            'id' => $entry['id'],
            'profile_id' => $entry['profile_id'],
            'name' => $entry['profile']['name'] ?? null,
            'tagline' => $entry['profile']['tagline'] ?? null,
            'billing_tier' => $entry['billing_tier'],
            'role_label' => $entry['role_label'] ?? null,
            'is_hidden' => (bool) ($entry['is_hidden'] ?? false),
        ];
    }

    public static function scheduleItem(array $item, string $timezone): array
    {
        return [
            'id' => $item['id'],
            'title' => $item['title'] ?? null,
            'starts_at' => self::localDate($item['starts_at'] ?? null, $timezone),
            'ends_at' => self::localDate($item['ends_at'] ?? null, $timezone),
            'space_id' => $item['event_space_id'] ?? null,
            'lineup_entry_ids' => $item['lineup_entry_ids'] ?? [],
        ];
    }

    public static function statsPeriod(array $stats): array
    {
        return [
            'from' => substr((string) $stats['start_date'], 0, 10),
            'to' => substr((string) $stats['end_date'], 0, 10),
        ];
    }

    /**
     * @return array{daily: list<array>, daily_truncated: bool}
     */
    public static function dailyStats(array $stats): array
    {
        $days = array_values($stats['daily_stats'] ?? []);

        return [
            'daily' => array_map(
                static fn (array $day): array => ['date' => substr((string) $day['date'], 0, 10), ...Arr::except($day, 'date')],
                array_slice($days, -self::MAX_DAILY_ROWS),
            ),
            'daily_truncated' => count($days) > self::MAX_DAILY_ROWS,
        ];
    }

    /**
     * @param  list<string>  $utcColumns
     * @return array{rows: list<array>, total_rows: int, truncated: bool}
     */
    public static function reportRows(array $rows, array $utcColumns, string $timezone, int $maxRows): array
    {
        $present = static fn (array $row): array => collect($row)
            ->map(static fn (mixed $value, string $column): mixed => in_array($column, $utcColumns, true) && is_string($value)
                ? self::localDate($value, $timezone)
                : $value)
            ->all();

        return [
            'rows' => array_map($present, array_slice(array_values($rows), 0, $maxRows)),
            'total_rows' => count($rows),
            'truncated' => count($rows) > $maxRows,
        ];
    }

    public static function widget(array $widget): array
    {
        return [
            'id' => $widget['id'],
            'name' => $widget['name'],
            'type' => $widget['type'],
            'short_id' => $widget['short_id'],
            'embed_code' => WidgetEmbedCode::forWidget($widget),
        ];
    }
}
