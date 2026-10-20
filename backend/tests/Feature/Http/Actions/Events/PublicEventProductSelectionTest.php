<?php

namespace Tests\Feature\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\AccountConfiguration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class PublicEventProductSelectionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $categoryA;

    private int $categoryB;

    private int $categoryEmpty;

    private int $ticket;

    private int $otherTicket;

    private int $addon;

    private int $merch;

    private int $productOrder = 0;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->insertOccurrence();

        $this->categoryA = $this->insertCategory('Tickets', 1);
        $this->categoryB = $this->insertCategory('Extras', 2);
        $this->categoryEmpty = $this->insertCategory('Coming soon', 3);

        $this->ticket = $this->insertCategorisedProduct($this->categoryA);
        $this->otherTicket = $this->insertCategorisedProduct($this->categoryA);
        $this->addon = $this->insertCategorisedProduct($this->categoryB);
        $this->merch = $this->insertCategorisedProduct($this->categoryB);

        DB::table('product_addons')->insert([
            'product_id' => $this->ticket,
            'addon_product_id' => $this->addon,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_without_a_selection_every_category_and_product_is_returned(): void
    {
        $this->assertSame([
            $this->categoryA => [$this->ticket, $this->otherTicket],
            $this->categoryB => [$this->addon, $this->merch],
            $this->categoryEmpty => [],
        ], $this->fetch(''));
    }

    public function test_selecting_a_product_keeps_its_addons_and_drops_empty_categories(): void
    {
        $this->assertSame([
            $this->categoryA => [$this->ticket],
            $this->categoryB => [$this->addon],
        ], $this->fetch("?product_ids={$this->ticket},abc,-4,0"));

        $this->assertSame([
            $this->categoryB => [$this->merch],
        ], $this->fetch("?product_ids[]={$this->merch}"));
    }

    public function test_selecting_categories_keeps_them_even_when_empty(): void
    {
        $this->assertSame([
            $this->categoryB => [$this->addon, $this->merch],
            $this->categoryEmpty => [],
        ], $this->fetch("?product_category_ids={$this->categoryB},{$this->categoryEmpty}"));
    }

    public function test_product_selection_applies_within_the_selected_categories(): void
    {
        $this->assertSame([
            $this->categoryA => [$this->otherTicket],
        ], $this->fetch("?product_category_ids={$this->categoryA}&product_ids={$this->otherTicket},{$this->merch}"));
    }

    public function test_selecting_a_hidden_product_never_reveals_it(): void
    {
        DB::table('products')->where('id', $this->merch)->update(['is_hidden' => true]);

        $this->assertSame([], array_merge(...array_values($this->fetch("?product_ids={$this->merch}"))));
    }

    private function fetch(string $query): array
    {
        return collect($this->getJson("/public/events/{$this->eventId}$query")->assertOk()->json('data.product_categories') ?? [])
            ->mapWithKeys(fn (array $category) => [
                $category['id'] => collect($category['products'] ?? [])->pluck('id')->all(),
            ])
            ->all();
    }

    private function insertCategory(string $name, int $order): int
    {
        return DB::table('product_categories')->insertGetId([
            'name' => $name,
            'event_id' => $this->eventId,
            'order' => $order,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertCategorisedProduct(int $categoryId): int
    {
        $productId = $this->insertProduct();
        DB::table('products')->where('id', $productId)->update([
            'product_category_id' => $categoryId,
            'order' => ++$this->productOrder,
        ]);
        $this->insertPrice($productId, null);

        return $productId;
    }
}
