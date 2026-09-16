<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class AddQuantityAppliesToToProductPricesTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const MIGRATION_PATH = __DIR__.'/../../../../database/migrations/2026_09_12_000001_add_quantity_applies_to_to_product_prices.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
    }

    public function test_existing_tiers_are_backfilled_to_event_and_new_tiers_default_to_occurrence(): void
    {
        $productId = $this->insertProduct();
        $existingPriceId = $this->insertPrice($productId, initialQuantity: 10);
        $occurrenceId = $this->insertOccurrence();
        DB::table('product_price_occurrence_overrides')->insert([
            'event_occurrence_id' => $occurrenceId,
            'product_price_id' => $existingPriceId,
            'price' => 12.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->down();
        $this->migration()->up();

        $this->assertSame('EVENT', DB::table('product_prices')->where('id', $existingPriceId)->value('quantity_applies_to'));

        $newPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 10.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame('OCCURRENCE', DB::table('product_prices')->where('id', $newPriceId)->value('quantity_applies_to'));
        $this->assertNull(DB::table('product_price_occurrence_overrides')->where('product_price_id', $existingPriceId)->value('quantity_available'));
    }

    public function test_rolling_back_drops_quantity_only_overrides(): void
    {
        $productId = $this->insertProduct();
        $priceId = $this->insertPrice($productId, initialQuantity: 10);
        $occurrenceId = $this->insertOccurrence();
        DB::table('product_price_occurrence_overrides')->insert([
            ['event_occurrence_id' => $occurrenceId, 'product_price_id' => $priceId, 'price' => null, 'quantity_available' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $pricedOccurrenceId = $this->insertOccurrence(daysAhead: 2);
        DB::table('product_price_occurrence_overrides')->insert([
            ['event_occurrence_id' => $pricedOccurrenceId, 'product_price_id' => $priceId, 'price' => 9.00, 'quantity_available' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->migration()->down();

        $this->assertSame(1, DB::table('product_price_occurrence_overrides')->where('product_price_id', $priceId)->count());

        $this->migration()->up();
    }

    private function migration(): Migration
    {
        return require self::MIGRATION_PATH;
    }
}
