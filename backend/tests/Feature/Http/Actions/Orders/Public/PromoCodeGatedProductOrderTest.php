<?php

namespace Tests\Feature\Http\Actions\Orders\Public;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\AccountConfiguration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class PromoCodeGatedProductOrderTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const CODE = 'member-code';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $promoCodeId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);
        DB::table('products')->where('id', $this->productId)->update(['is_hidden_without_promo_code' => true]);

        $this->promoCodeId = DB::table('promo_codes')->insertGetId([
            'code' => self::CODE,
            'event_id' => $this->eventId,
            'discount' => 0,
            'discount_type' => PromoCodeDiscountTypeEnum::NONE->name,
            'discount_applies_to' => PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name,
            'applicable_product_ids' => json_encode([$this->productId]),
            'max_allowed_usages' => 1,
            'order_usage_count' => 0,
            'attendee_usage_count' => 0,
            'created_at' => now(),
        ]);
    }

    public function test_the_hidden_product_needs_the_code(): void
    {
        $this->postOrder('first-session', null)->assertNotFound();
    }

    public function test_a_single_use_code_held_by_a_checkout_in_progress_cannot_unlock_the_product_again(): void
    {
        $this->postOrder('first-session', self::CODE)->assertCreated();

        $this->postOrder('second-session', self::CODE)->assertNotFound();

        $this->assertSame(1, DB::table('orders')->where('event_id', $this->eventId)->whereNull('deleted_at')->count());
    }

    public function test_a_code_freed_by_an_abandoned_checkout_unlocks_the_product_again(): void
    {
        $this->postOrder('first-session', self::CODE)->assertCreated();
        DB::table('orders')->where('event_id', $this->eventId)->update(['status' => OrderStatus::ABANDONED->name]);

        $shortId = $this->postOrder('second-session', self::CODE)->assertCreated()->json('data.short_id');

        $this->assertSame($this->promoCodeId, DB::table('orders')->where('short_id', $shortId)->value('promo_code_id'));
    }

    public function test_a_code_that_does_not_apply_to_the_product_cannot_unlock_it(): void
    {
        DB::table('promo_codes')->where('id', $this->promoCodeId)->update([
            'applicable_product_ids' => json_encode([$this->productId + 1000]),
        ]);

        $this->postOrder('first-session', self::CODE)->assertNotFound();
    }

    private function postOrder(string $sessionIdentifier, ?string $promoCode): TestResponse
    {
        foreach ($this->app['router']->getRoutes()->getRoutes() as $route) {
            $route->flushController();
        }

        return $this->postJson("/public/events/{$this->eventId}/order?session_identifier={$sessionIdentifier}", array_filter([
            'promo_code' => $promoCode,
            'products' => [[
                'product_id' => $this->productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [['price_id' => $this->priceId, 'quantity' => 1]],
            ]],
        ], static fn ($value) => $value !== null));
    }
}
