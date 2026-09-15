<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\Product;

use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\Http\Request\Product\UpsertProductRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpsertProductRequestTest extends TestCase
{
    public function test_sequential_release_requires_quantity_on_all_but_last_tier(): void
    {
        $validator = $this->validate([
            'sequential_tier_release_enabled' => true,
            'prices' => [
                ['price' => 10, 'label' => 'Early bird', 'initial_quantity_available' => null],
                ['price' => 20, 'label' => 'Regular', 'initial_quantity_available' => 50],
                ['price' => 30, 'label' => 'Late'],
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('prices.0.initial_quantity_available'));
        $this->assertFalse($validator->errors()->has('prices.1.initial_quantity_available'));
        $this->assertFalse($validator->errors()->has('prices.2.initial_quantity_available'));
    }

    public function test_sequential_release_passes_when_all_but_last_tier_have_quantity(): void
    {
        $validator = $this->validate([
            'sequential_tier_release_enabled' => true,
            'prices' => [
                ['price' => 10, 'label' => 'Early bird', 'initial_quantity_available' => 25],
                ['price' => 20, 'label' => 'Regular'],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_quantity_rule_is_ignored_when_sequential_release_is_off(): void
    {
        $validator = $this->validate([
            'sequential_tier_release_enabled' => false,
            'prices' => [
                ['price' => 10, 'label' => 'Early bird'],
                ['price' => 20, 'label' => 'Regular'],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_quantity_rule_is_ignored_for_non_tiered_products(): void
    {
        $validator = $this->validate([
            'type' => ProductPriceType::PAID->name,
            'sequential_tier_release_enabled' => true,
            'prices' => [
                ['price' => 10],
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    private function validate(array $overrides): \Illuminate\Validation\Validator
    {
        $request = new UpsertProductRequest;
        $request->merge(array_merge([
            'title' => 'Ticket',
            'type' => ProductPriceType::TIERED->name,
            'product_type' => ProductType::TICKET->name,
            'product_category_id' => 1,
        ], $overrides));

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }
}
