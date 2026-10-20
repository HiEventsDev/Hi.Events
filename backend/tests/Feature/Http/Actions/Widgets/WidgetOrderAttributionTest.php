<?php

namespace Tests\Feature\Http\Actions\Widgets;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Models\AccountConfiguration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class WidgetOrderAttributionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

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
    }

    public function test_orders_are_attributed_to_the_events_own_or_its_organizers_widgets(): void
    {
        $eventWidget = $this->insertWidget('wgt_event', $this->organizerId, $this->eventId);
        $organizerWidget = $this->insertWidget('wgt_organizer', $this->organizerId, null);

        $this->assertSame($eventWidget, $this->orderWidgetId('wgt_event'));
        $this->assertSame($organizerWidget, $this->orderWidgetId('wgt_organizer'));
    }

    public function test_unknown_foreign_and_deleted_widgets_are_ignored(): void
    {
        $siblingEventId = $this->insertEvent(EventType::SINGLE->name);
        $this->insertWidget('wgt_sibling', $this->organizerId, $siblingEventId);
        $this->insertWidget('wgt_deleted', $this->organizerId, $this->eventId, deleted: true);
        $otherOrganizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Other',
            'email' => 'other@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertWidget('wgt_other_org', $otherOrganizerId, null);

        $this->assertNull($this->orderWidgetId('wgt_sibling'));
        $this->assertNull($this->orderWidgetId('wgt_deleted'));
        $this->assertNull($this->orderWidgetId('wgt_other_org'));
        $this->assertNull($this->orderWidgetId('wgt_missing'));
        $this->assertNull($this->orderWidgetId(null));
    }

    public function test_rejects_an_overlong_widget_id(): void
    {
        $this->postOrder(str_repeat('x', 51))->assertUnprocessable()->assertJsonValidationErrors('widget_id');
    }

    private function orderWidgetId(?string $widgetShortId): ?int
    {
        $orderShortId = $this->postOrder($widgetShortId)->assertCreated()->json('data.short_id');

        return DB::table('orders')->where('short_id', $orderShortId)->value('widget_id');
    }

    private function postOrder(?string $widgetShortId)
    {
        return $this->postJson("/public/events/{$this->eventId}/order", array_filter([
            'widget_id' => $widgetShortId,
            'products' => [[
                'product_id' => $this->productId,
                'event_occurrence_id' => $this->occurrenceId,
                'quantities' => [['price_id' => $this->priceId, 'quantity' => 1]],
            ]],
        ], static fn ($value) => $value !== null));
    }

    private function insertWidget(string $shortId, int $organizerId, ?int $eventId, bool $deleted = false): int
    {
        return DB::table('widgets')->insertGetId([
            'short_id' => $shortId,
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'event_id' => $eventId,
            'name' => 'Widget',
            'type' => $eventId === null ? 'EVENTS' : 'TICKETS',
            'settings' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deleted ? now() : null,
        ]);
    }
}
