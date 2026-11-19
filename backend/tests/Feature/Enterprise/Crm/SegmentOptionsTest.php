<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentOptionsTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithCrmApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmApi();
    }

    public function test_events_carry_their_next_date_and_upcoming_events_come_first(): void
    {
        $pastEventId = $this->createEvent($this->accountId, $this->organizerId, 'Last Year Gala');
        $this->insertOccurrence($pastEventId, now()->subYear());
        $this->insertOccurrence($pastEventId, now()->subMonths(11));
        $soonEventId = $this->createEvent($this->accountId, $this->organizerId, 'Soon Show');
        $this->insertOccurrence($soonEventId, now()->subMonth());
        $soonOccurrence = now()->addDays(2)->startOfMinute();
        $this->insertOccurrence($soonEventId, $soonOccurrence);
        $this->insertOccurrence($soonEventId, now()->addDays(30));
        DB::table('events')->where('id', $soonEventId)->update(['status' => 'LIVE']);

        $events = $this->crm('GET', 'segment-options', [], $this->ownerHeaders())->assertOk()->json('data.events');

        $this->assertSame([$soonEventId, $this->eventId, $pastEventId], array_column($events, 'id'));
        $this->assertSame('LIVE', $events[0]['status']);
        $this->assertSame($soonOccurrence->toIso8601String(), Carbon::parse($events[0]['starts_at'])->toIso8601String());
        $this->assertSame(now()->subMonths(11)->toDateString(), Carbon::parse($events[2]['starts_at'])->toDateString());
        $this->assertSame([], $this->crm('GET', 'segment-options', [], $this->ownerHeaders())->json('data.products'));
    }

    public function test_events_can_be_searched_and_selected_ids_are_always_included(): void
    {
        $oldEventId = $this->createEvent($this->accountId, $this->organizerId, 'Harvest Ball 2019');
        $this->createEvent($this->accountId, $this->organizerId, 'Spring Fair');

        $events = $this->crm('GET', 'segment-options?'.http_build_query(['event_query' => 'harvest', 'event_ids' => [$this->eventId, $this->otherEventId]]), [], $this->ownerHeaders())
            ->assertOk()
            ->json('data.events');

        $this->assertEqualsCanonicalizing([$oldEventId, $this->eventId], array_column($events, 'id'));
    }

    public function test_ticket_types_of_old_events_can_be_found_by_searching(): void
    {
        $oldEventId = $this->createEvent($this->accountId, $this->organizerId, 'Harvest Ball 2019');
        $oldProductId = DB::table('products')->insertGetId([
            'title' => 'Early Bird',
            'event_id' => $oldEventId,
            'product_type' => 'TICKET',
            'type' => 'PAID',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        [$foreignProductId] = $this->insertTicket($this->otherEventId);

        $byProduct = $this->crm('GET', 'segment-options?'.http_build_query(['with_products' => 1, 'product_query' => 'early']), [], $this->ownerHeaders())
            ->assertOk()
            ->json('data.products');
        $byEvent = $this->crm('GET', 'segment-options?'.http_build_query(['with_products' => 1, 'product_query' => 'harvest']), [], $this->ownerHeaders())
            ->json('data.products');
        $selected = $this->crm('GET', 'segment-options?'.http_build_query(['with_products' => 1, 'product_query' => 'nothing matches', 'product_ids' => [$this->productId, $foreignProductId]]), [], $this->ownerHeaders())
            ->json('data.products');

        $this->assertSame([['id' => $oldProductId, 'title' => 'Early Bird', 'event_id' => $oldEventId, 'event_title' => 'Harvest Ball 2019']], $byProduct);
        $this->assertSame([$oldProductId], array_column($byEvent, 'id'));
        $this->assertSame([$this->productId], array_column($selected, 'id'));
    }
}
