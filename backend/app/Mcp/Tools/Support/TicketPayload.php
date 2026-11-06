<?php

namespace HiEvents\Mcp\Tools\Support;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;
use Illuminate\Support\Arr;

class TicketPayload
{
    public const TYPES = [
        ProductPriceType::FREE->name,
        ProductPriceType::PAID->name,
        ProductPriceType::DONATION->name,
        ProductPriceType::TIERED->name,
    ];

    private const TIER_FIELDS = [
        'label' => 'label',
        'price' => 'price',
        'quantity_available' => 'initial_quantity_available',
        'sale_start_date' => 'sale_start_date',
        'sale_end_date' => 'sale_end_date',
        'is_hidden' => 'is_hidden',
    ];

    private const COPIED_PRODUCT_FIELDS = [
        'title',
        'type',
        'product_type',
        'description',
        'product_category_id',
        'max_per_order',
        'min_per_order',
        'initial_quantity_available',
        'is_hidden',
        'hide_before_sale_start_date',
        'hide_after_sale_end_date',
        'hide_when_sold_out',
        'sequential_tier_release_enabled',
        'start_collapsed',
        'show_quantity_remaining',
        'is_hidden_without_promo_code',
        'is_addon_only',
        'is_highlighted',
        'highlight_message',
        'waitlist_enabled',
    ];

    public static function tiersSchema(JsonSchema $schema): ArrayType
    {
        return $schema->array()->items($schema->object([
            'id' => $schema->integer()->description('Existing tier id, when updating. Leave out for a new tier.'),
            'label' => $schema->string()->description('Tier name, e.g. "Early bird". Required for a new tier.'),
            'price' => $schema->number()->description('Required for a new tier.'),
            'quantity_available' => $schema->integer()->nullable()->description('How many at this price; null or left out for unlimited on a new tier.'),
            'sale_start_date' => $schema->string()->nullable()->description(McpPresenter::LOCAL_DATE_DESCRIPTION),
            'sale_end_date' => $schema->string()->nullable()->description(McpPresenter::LOCAL_DATE_DESCRIPTION),
            'is_hidden' => $schema->boolean(),
        ]));
    }

    public static function forNewProduct(array $input, ?int $categoryId, string $defaultProductType): array
    {
        return [
            ...Arr::only($input, ['title', 'type', 'description', 'sale_start_date', 'sale_end_date', 'min_per_order', 'max_per_order', 'is_hidden']),
            'product_type' => array_key_exists('product_type', $input) ? $input['product_type'] : $defaultProductType,
            'product_category_id' => $categoryId,
            'prices' => ($input['type'] ?? null) === ProductPriceType::TIERED->name
                ? self::tierPrices($input['tiers'] ?? [])
                : [[
                    'price' => array_key_exists('price', $input) ? $input['price'] : 0,
                    'initial_quantity_available' => $input['quantity_available'] ?? null,
                ]],
        ];
    }

    public static function tierPrices(array $tiers): array
    {
        return collect($tiers)
            ->map(static fn (array $tier): array => array_filter(
                ['id' => $tier['id'] ?? null, ...collect(self::TIER_FIELDS)->mapWithKeys(static fn (string $priceField, string $tierField): array => [$priceField => $tier[$tierField] ?? null])->all()],
                static fn (mixed $value): bool => $value !== null,
            ))
            ->values()
            ->all();
    }

    public static function mergeTiers(array $existingPrices, array $tiers): array
    {
        $existingById = collect($existingPrices)->keyBy('id');

        return collect($tiers)
            ->map(static function (array $tier) use ($existingById): array {
                $existing = isset($tier['id']) ? $existingById->get($tier['id']) : null;

                if ($existing === null) {
                    return self::tierPrices([$tier])[0];
                }

                foreach (self::TIER_FIELDS as $tierField => $priceField) {
                    if (array_key_exists($tierField, $tier)) {
                        $existing[$priceField] = $tier[$tierField];
                    }
                }

                return $existing;
            })
            ->values()
            ->all();
    }

    public static function fromExistingProduct(array $product, string $timezone): array
    {
        return [
            ...array_intersect_key($product, array_flip(self::COPIED_PRODUCT_FIELDS)),
            'sale_start_date' => McpPresenter::localInputDate($product['sale_start_date'] ?? null, $timezone),
            'sale_end_date' => McpPresenter::localInputDate($product['sale_end_date'] ?? null, $timezone),
            'tax_and_fee_ids' => collect($product['taxes_and_fees'] ?? [])->pluck('id')->values()->all(),
            'addon_product_ids' => $product['addon_product_ids'] ?? [],
            'prices' => collect($product['prices'] ?? [])
                ->map(static fn (array $price): array => [
                    'id' => $price['id'],
                    'label' => $price['label'] ?? null,
                    'price' => $price['price'],
                    'initial_quantity_available' => $price['initial_quantity_available'] ?? null,
                    'sale_start_date' => McpPresenter::localInputDate($price['sale_start_date'] ?? null, $timezone),
                    'sale_end_date' => McpPresenter::localInputDate($price['sale_end_date'] ?? null, $timezone),
                    'is_hidden' => $price['is_hidden'] ?? false,
                    'quantity_applies_to' => $price['quantity_applies_to'] ?? null,
                ])
                ->values()
                ->all(),
        ];
    }
}
