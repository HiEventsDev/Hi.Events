<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductQuantityAppliesTo;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Services\Application\Handlers\Event\DTO\GetPublicOrganizerEventsDTO;
use HiEvents\Services\Application\Handlers\Event\GetPublicEventsHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class GetPublicEventsHandlerTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.homepage_product_quantities_cache_ttl' => 60]);
        $this->insertAccountAndOrganizer();
    }

    public function test_listing_queries_do_not_grow_with_the_number_of_events_whether_or_not_quantities_are_cached(): void
    {
        $this->insertListedEvents(2);
        $fewCold = $this->countQueries(flushCache: true);
        $fewWarm = $this->countQueries(flushCache: false);

        $this->insertListedEvents(6);
        $manyCold = $this->countQueries(flushCache: true);
        $manyWarm = $this->countQueries(flushCache: false);

        $this->assertSame($fewCold, $manyCold);
        $this->assertSame($fewWarm, $manyWarm);
        $this->assertLessThan($fewCold, $fewWarm);
    }

    private function insertListedEvents(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->eventId = $this->insertEvent($i % 2 === 0 ? EventType::SINGLE->name : EventType::RECURRING->name);
            DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
            $this->insertOccurrence(daysAhead: 3);

            $categoryId = DB::table('product_categories')->insertGetId([
                'name' => 'Tickets',
                'event_id' => $this->eventId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productId = $this->insertProduct();
            DB::table('products')->where('id', $productId)->update(['product_category_id' => $categoryId]);
            $this->insertPrice($productId, initialQuantity: 50, appliesTo: ProductQuantityAppliesTo::EVENT->name);
            $this->insertEventSeatMap($this->seatMapFixture('club'), ['b_premium' => [$productId]]);
        }
    }

    private function countQueries(bool $flushCache): int
    {
        if ($flushCache) {
            $this->app->make('cache')->flush();
        }
        $this->app->forgetScopedInstances();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $events = $this->app->make(GetPublicEventsHandler::class)->handle(new GetPublicOrganizerEventsDTO(
            organizerId: $this->organizerId,
            queryParams: QueryParamsDTO::fromArray(['per_page' => 100]),
        ));

        $this->assertNotEmpty($events->items());

        return $queries;
    }
}
