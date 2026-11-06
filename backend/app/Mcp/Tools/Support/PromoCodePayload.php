<?php

namespace HiEvents\Mcp\Tools\Support;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PromoCodePayload
{
    public const FIELDS = [
        'code',
        'discount_type',
        'discount',
        'discount_applies_to',
        'applicable_product_ids',
        'expiry_date',
        'max_allowed_usages',
    ];

    public static function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('The code buyers enter, 2-50 characters. Stored in lower case.'),
            'discount_type' => $schema->string()->enum(PromoCodeDiscountTypeEnum::valuesArray())->description('PERCENTAGE, FIXED (an amount off in the event currency) or NONE (unlocks hidden tickets without a discount).'),
            'discount' => $schema->number()->description('Percentage (up to 100) or amount off.'),
            'discount_applies_to' => $schema->string()->enum(PromoCodeDiscountAppliesToEnum::valuesArray())->description('EACH_PRODUCT takes the discount off every ticket; ORDER takes it once off the order.'),
            'applicable_product_ids' => $schema->array()->items($schema->integer())->description('Tickets the code works for. An empty list means all tickets.'),
            'expiry_date' => $schema->string()->nullable()->description('When the code stops working. '.McpPresenter::LOCAL_DATE_DESCRIPTION),
            'max_allowed_usages' => $schema->integer()->nullable()->description('How many times it can be used. Null for unlimited.'),
        ];
    }

    public static function fromExisting(array $promoCode, string $timezone): array
    {
        return [
            'code' => $promoCode['code'],
            'discount_type' => $promoCode['discount_type'],
            'discount' => $promoCode['discount'] ?? 0,
            'discount_applies_to' => $promoCode['discount_applies_to'] ?? PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name,
            'applicable_product_ids' => $promoCode['applicable_product_ids'] ?? [],
            'expiry_date' => McpPresenter::localInputDate($promoCode['expiry_date'] ?? null, $timezone),
            'max_allowed_usages' => $promoCode['max_allowed_usages'] ?? null,
        ];
    }

    public static function present(array $promoCode, string $timezone): array
    {
        return [
            'id' => $promoCode['id'],
            'code' => $promoCode['code'],
            'discount_type' => $promoCode['discount_type'],
            'discount' => $promoCode['discount'] ?? 0,
            'discount_applies_to' => $promoCode['discount_applies_to'] ?? null,
            'applicable_product_ids' => $promoCode['applicable_product_ids'] ?? [],
            'expiry_date' => McpPresenter::localDate($promoCode['expiry_date'] ?? null, $timezone),
            'max_allowed_usages' => $promoCode['max_allowed_usages'] ?? null,
            'order_usage_count' => $promoCode['order_usage_count'] ?? 0,
        ];
    }
}
