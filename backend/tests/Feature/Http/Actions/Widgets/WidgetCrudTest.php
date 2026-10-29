<?php

namespace Tests\Feature\Http\Actions\Widgets;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class WidgetCrudTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    private int $otherEventId;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId, 'Main');
        $this->otherEventId = $this->createEvent($this->accountId, $this->organizerId, 'Other');
        $this->headers = $this->headersFor($this->owner, $this->accountId);
    }

    public function test_owner_creates_lists_updates_and_deletes_event_widgets(): void
    {
        $categoryId = $this->insertCategory($this->eventId);
        $productId = $this->insertProduct($this->eventId, $categoryId);

        $created = $this->postJson("/events/$this->eventId/widgets", [
            'name' => '  Homepage tickets ',
            'type' => 'TICKETS',
            'settings' => [
                'primary_color' => '#ff0000',
                'products' => [$productId],
                'categories' => [$categoryId],
                'sections' => ['tickets', 'about'],
                'button_text' => null,
            ],
        ], $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Homepage tickets')
            ->assertJsonPath('data.type', 'TICKETS')
            ->assertJsonPath('data.event_id', $this->eventId)
            ->assertJsonPath('data.organizer_id', $this->organizerId)
            ->assertJsonPath('data.settings', [
                'primary_color' => '#ff0000',
                'products' => [$productId],
                'categories' => [$categoryId],
                'sections' => ['tickets', 'about'],
            ])
            ->assertJsonPath('data.stats.views', 0)
            ->assertJsonPath('data.stats.orders', 0)
            ->json('data');

        $this->assertMatchesRegularExpression('/^wgt_[A-Za-z0-9]{13}$/', $created['short_id']);

        $this->getJson("/events/$this->eventId/widgets", $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created['id']);

        $this->putJson("/events/$this->eventId/widgets/{$created['id']}", [
            'name' => 'Buy button',
            'type' => 'BUTTON',
            'settings' => [],
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.type', 'BUTTON')
            ->assertJsonPath('data.short_id', $created['short_id']);

        $this->assertSame('{}', json_encode($this->getJson("/events/$this->eventId/widgets", $this->headers)->getData()->data[0]->settings));

        $this->deleteJson("/events/$this->eventId/widgets/{$created['id']}", [], $this->headers)->assertNoContent();
        $this->getJson("/events/$this->eventId/widgets", $this->headers)->assertOk()->assertJsonCount(0, 'data');
        $this->assertSoftDeleted('widgets', ['id' => $created['id']]);
    }

    public function test_owner_manages_organizer_event_listing_widgets_separately_from_event_widgets(): void
    {
        $this->createEventWidget($this->eventId);

        $widgetId = $this->postJson("/organizers/$this->organizerId/widgets", [
            'name' => 'All events',
            'type' => 'EVENTS',
            'settings' => ['layout' => 'grid', 'limit' => 12, 'event_category' => 'MUSIC', 'show_past' => false, 'open_in' => 'page'],
        ], $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.event_id', null)
            ->assertJsonPath('data.settings.layout', 'grid')
            ->json('data.id');

        $this->getJson("/organizers/$this->organizerId/widgets", $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $widgetId);

        $this->putJson("/organizers/$this->organizerId/widgets/$widgetId", [
            'name' => 'Upcoming',
            'type' => 'EVENTS',
            'settings' => ['layout' => 'list'],
        ], $this->headers)->assertOk()->assertJsonPath('data.name', 'Upcoming');

        $this->postJson("/organizers/$this->organizerId/widgets", ['name' => 'x', 'type' => 'TICKETS'], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
        $this->postJson("/organizers/$this->organizerId/widgets", [
            'name' => 'x', 'type' => 'EVENTS', 'settings' => ['products' => [1]],
        ], $this->headers)->assertUnprocessable()->assertJsonValidationErrors('settings');

        $this->deleteJson("/organizers/$this->organizerId/widgets/$widgetId", [], $this->headers)->assertNoContent();
        $this->assertSoftDeleted('widgets', ['id' => $widgetId]);
    }

    public function test_rejects_invalid_event_widget_payloads(): void
    {
        $categoryId = $this->insertCategory($this->eventId);
        $productId = $this->insertProduct($this->eventId, $categoryId);
        $foreignCategory = $this->insertCategory($this->otherEventId);
        $foreignProduct = $this->insertProduct($this->otherEventId, $foreignCategory);
        $foreignOccurrence = $this->insertOccurrence($this->otherEventId);
        $deletedProduct = $this->insertProduct($this->eventId, $categoryId);
        DB::table('products')->where('id', $deletedProduct)->update(['deleted_at' => now()]);

        $post = fn (array $payload) => $this->postJson("/events/$this->eventId/widgets", ['name' => 'Widget', 'type' => 'TICKETS', ...$payload], $this->headers);

        $post(['type' => 'EVENTS'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $post(['settings' => ['onload' => 'alert(1)']])->assertUnprocessable()->assertJsonValidationErrors('settings');
        $post(['settings' => ['layout' => 'grid']])->assertUnprocessable()->assertJsonValidationErrors('settings');
        $post(['settings' => ['sections' => ['about', 'about']]])->assertUnprocessable()->assertJsonValidationErrors('settings.sections.1');
        $post(['settings' => ['products' => [$productId, $foreignProduct]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.products' => 'One or more selected products do not belong to this event.']);
        $post(['settings' => ['products' => [$deletedProduct]]])->assertUnprocessable()->assertJsonValidationErrors('settings.products');
        $post(['settings' => ['products' => [999999999]]])->assertUnprocessable()->assertJsonValidationErrors('settings.products');
        $post(['settings' => ['categories' => [$foreignCategory]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.categories' => 'One or more selected categories do not belong to this event.']);
        $post(['settings' => ['occurrence_id' => $foreignOccurrence]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.occurrence_id' => 'The selected date does not belong to this event.']);

        $this->assertDatabaseMissing('widgets', ['event_id' => $this->eventId]);
    }

    public function test_updating_rejects_newly_selected_ids_that_are_not_on_the_event(): void
    {
        $categoryId = $this->insertCategory($this->eventId);
        $productId = $this->insertProduct($this->eventId, $categoryId);
        $staleProductId = $this->insertProduct($this->eventId, $categoryId);
        $newlyDeletedProductId = $this->insertProduct($this->eventId, $categoryId);
        $foreignCategory = $this->insertCategory($this->otherEventId);
        $foreignProduct = $this->insertProduct($this->otherEventId, $foreignCategory);
        $foreignOccurrence = $this->insertOccurrence($this->otherEventId);
        $storedSettings = ['products' => [$productId, $staleProductId]];
        $widgetId = $this->createEventWidget($this->eventId, $storedSettings);
        DB::table('products')->whereIn('id', [$staleProductId, $newlyDeletedProductId])->update(['deleted_at' => now()]);

        $put = fn (array $settings) => $this->putJson("/events/$this->eventId/widgets/$widgetId", [
            'name' => 'Renamed',
            'type' => 'TICKETS',
            'settings' => $settings,
        ], $this->headers);

        $put(['products' => [$productId, $staleProductId, $foreignProduct]])->assertUnprocessable()->assertJsonValidationErrors('settings.products');
        $put(['products' => [$productId, $newlyDeletedProductId]])->assertUnprocessable()->assertJsonValidationErrors('settings.products');
        $put(['products' => [$productId, 999999999]])->assertUnprocessable()->assertJsonValidationErrors('settings.products');
        $put(['categories' => [$foreignCategory]])->assertUnprocessable()->assertJsonValidationErrors('settings.categories');
        $put(['occurrence_id' => $foreignOccurrence])->assertUnprocessable()->assertJsonValidationErrors('settings.occurrence_id');

        $this->assertDatabaseHas('widgets', ['id' => $widgetId, 'name' => 'Widget']);
        $this->assertSame($storedSettings, json_decode(DB::table('widgets')->where('id', $widgetId)->value('settings'), true));
    }

    public function test_saving_drops_selections_deleted_since_the_widget_was_saved(): void
    {
        $categoryId = $this->insertCategory($this->eventId);
        $deletedCategoryId = $this->insertCategory($this->eventId);
        $keptProductId = $this->insertProduct($this->eventId, $categoryId);
        $deletedProductId = $this->insertProduct($this->eventId, $categoryId);
        $deletedOccurrenceId = $this->insertOccurrence($this->eventId);

        $widget = $this->postJson("/events/$this->eventId/widgets", [
            'name' => 'VIP only',
            'type' => 'TICKETS',
            'settings' => [
                'products' => [$keptProductId, $deletedProductId],
                'categories' => [$deletedCategoryId],
                'occurrence_id' => $deletedOccurrenceId,
                'hide_promo_code' => true,
            ],
        ], $this->headers)->assertCreated()->json('data');

        DB::table('products')->where('id', $deletedProductId)->update(['deleted_at' => now()]);
        DB::table('product_categories')->where('id', $deletedCategoryId)->update(['deleted_at' => now()]);
        DB::table('event_occurrences')->where('id', $deletedOccurrenceId)->update(['deleted_at' => now()]);

        $this->putJson("/events/$this->eventId/widgets/{$widget['id']}", [
            'name' => 'Renamed',
            'type' => 'TICKETS',
            'settings' => $widget['settings'],
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.settings', ['products' => [$keptProductId], 'hide_promo_code' => true]);

        $this->assertSame(
            ['products' => [$keptProductId], 'hide_promo_code' => true],
            json_decode(DB::table('widgets')->where('id', $widget['id'])->value('settings'), true),
        );
    }

    public function test_saving_a_widget_whose_selected_tickets_were_all_deleted_clears_the_selection(): void
    {
        $categoryId = $this->insertCategory($this->eventId);
        $firstProductId = $this->insertProduct($this->eventId, $categoryId);
        $secondProductId = $this->insertProduct($this->eventId, $categoryId);
        $widget = $this->postJson("/events/$this->eventId/widgets", [
            'name' => 'VIP only',
            'type' => 'TICKETS',
            'settings' => ['products' => [$firstProductId, $secondProductId], 'button_text' => 'Buy VIP'],
        ], $this->headers)->assertCreated()->json('data');

        DB::table('products')->whereIn('id', [$firstProductId, $secondProductId])->update(['deleted_at' => now()]);

        $this->putJson("/events/$this->eventId/widgets/{$widget['id']}", [
            'name' => 'VIP only renamed',
            'type' => 'TICKETS',
            'settings' => $widget['settings'],
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'VIP only renamed')
            ->assertJsonPath('data.settings', ['button_text' => 'Buy VIP']);

        $this->assertSame(
            ['button_text' => 'Buy VIP'],
            json_decode(DB::table('widgets')->where('id', $widget['id'])->value('settings'), true),
        );
    }

    public function test_widget_ids_are_scoped_to_the_parent_in_the_route(): void
    {
        $otherWidget = $this->createEventWidget($this->otherEventId);
        $organizerWidget = $this->postJson("/organizers/$this->organizerId/widgets", ['name' => 'All', 'type' => 'EVENTS'], $this->headers)
            ->json('data.id');

        $this->putJson("/events/$this->eventId/widgets/$otherWidget", ['name' => 'Hijack', 'type' => 'TICKETS'], $this->headers)->assertNotFound();
        $this->deleteJson("/events/$this->eventId/widgets/$otherWidget", [], $this->headers)->assertNotFound();
        $this->putJson("/events/$this->eventId/widgets/$organizerWidget", ['name' => 'Hijack', 'type' => 'TICKETS'], $this->headers)->assertNotFound();
        $this->putJson("/organizers/$this->organizerId/widgets/$otherWidget", ['name' => 'Hijack', 'type' => 'EVENTS'], $this->headers)->assertNotFound();
        $this->deleteJson("/organizers/$this->organizerId/widgets/$otherWidget", [], $this->headers)->assertNotFound();

        $this->assertDatabaseHas('widgets', ['id' => $otherWidget, 'name' => 'Widget', 'deleted_at' => null]);
    }

    public function test_viewers_can_list_widgets_without_revenue_but_cannot_change_them(): void
    {
        $widgetId = $this->createEventWidget($this->eventId);
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $headers = $this->headersFor($viewer, $this->accountId);

        $this->getJson("/events/$this->eventId/widgets", $headers)
            ->assertOk()
            ->assertJsonPath('data.0.stats.orders', 0)
            ->assertJsonMissingPath('data.0.stats.revenue');
        $this->getJson("/organizers/$this->organizerId/widgets", $headers)->assertOk();

        $this->postJson("/events/$this->eventId/widgets", ['name' => 'Nope', 'type' => 'TICKETS'], $headers)->assertForbidden();
        $this->putJson("/events/$this->eventId/widgets/$widgetId", ['name' => 'Nope', 'type' => 'TICKETS'], $headers)->assertForbidden();
        $this->deleteJson("/events/$this->eventId/widgets/$widgetId", [], $headers)->assertForbidden();
        $this->postJson("/organizers/$this->organizerId/widgets", ['name' => 'Nope', 'type' => 'EVENTS'], $headers)->assertForbidden();

        $this->assertDatabaseHas('widgets', ['id' => $widgetId, 'name' => 'Widget', 'deleted_at' => null]);
    }

    public function test_stats_count_only_completed_orders_and_split_revenue_net_of_refunds_by_currency(): void
    {
        $widgetId = $this->createEventWidget($this->eventId);
        $quietWidgetId = $this->createEventWidget($this->eventId);

        $this->insertOrder($widgetId, OrderStatus::COMPLETED, 'USD', 20.50);
        $this->insertOrder($widgetId, OrderStatus::COMPLETED, 'USD', 10.00);
        $this->insertOrder($widgetId, OrderStatus::COMPLETED, 'EUR', 5.25);
        $this->insertOrder($widgetId, OrderStatus::COMPLETED, 'EUR', 40.00, 40.00);
        $this->insertOrder($widgetId, OrderStatus::RESERVED, 'USD', 99.00);
        $this->insertOrder($widgetId, OrderStatus::CANCELLED, 'USD', 99.00);
        DB::table('widgets')->where('id', $widgetId)->update(['view_count' => 42]);
        Cache::put('widget_views_'.$widgetId, 3);

        $widgets = collect($this->getJson("/events/$this->eventId/widgets", $this->headers)->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(['views' => 45, 'orders' => 4, 'revenue' => ['EUR' => 5.25, 'USD' => 30.5]], $widgets[$widgetId]['stats']);
        $this->assertSame(['views' => 0, 'orders' => 0, 'revenue' => []], $widgets[$quietWidgetId]['stats']);
    }

    public function test_public_endpoint_serves_visible_widgets_and_counts_anonymous_views(): void
    {
        config(['app.homepage_views_update_batch_size' => 1]);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        $liveWidget = $this->createEventWidget($this->eventId, ['button_text' => 'Get tickets']);
        $draftWidget = $this->createEventWidget($this->otherEventId);
        $deletedWidget = $this->createEventWidget($this->eventId);
        $organizerWidget = $this->postJson("/organizers/$this->organizerId/widgets", ['name' => 'All', 'type' => 'EVENTS'], $this->headers)->json('data.id');
        $this->deleteJson("/events/$this->eventId/widgets/$deletedWidget", [], $this->headers)->assertNoContent();

        $shortId = fn (int $id) => DB::table('widgets')->where('id', $id)->value('short_id');
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        $this->getJson('/public/widgets/'.$shortId($liveWidget))
            ->assertOk()
            ->assertExactJson(['data' => [
                'short_id' => $shortId($liveWidget),
                'type' => 'TICKETS',
                'event_id' => $this->eventId,
                'organizer_id' => $this->organizerId,
                'settings' => ['button_text' => 'Get tickets'],
            ]]);
        $this->getJson('/public/widgets/'.$shortId($liveWidget))->assertOk();
        $this->assertSame(1, DB::table('widgets')->where('id', $liveWidget)->value('view_count'));

        $this->getJson('/public/widgets/'.$shortId($draftWidget))->assertNotFound();
        $this->getJson('/public/widgets/'.$shortId($deletedWidget))->assertNotFound();
        $this->getJson('/public/widgets/wgt_doesnotexist')->assertNotFound();
        $this->getJson('/public/widgets/'.$shortId($organizerWidget))->assertOk()->assertJsonPath('data.type', 'EVENTS');

        $this->getJson('/public/widgets/'.$shortId($draftWidget), $this->headersFor($this->owner, $this->accountId))->assertOk();
        $this->assertSame(0, DB::table('widgets')->where('id', $draftWidget)->value('view_count'));
    }

    private function createEventWidget(int $eventId, array $settings = []): int
    {
        return $this->postJson("/events/$eventId/widgets", [
            'name' => 'Widget',
            'type' => 'TICKETS',
            'settings' => $settings,
        ], $this->headersFor($this->owner, $this->accountId))->assertCreated()->json('data.id');
    }

    private function insertCategory(int $eventId): int
    {
        return DB::table('product_categories')->insertGetId([
            'name' => 'Tickets',
            'event_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertProduct(int $eventId, int $categoryId): int
    {
        return DB::table('products')->insertGetId([
            'title' => 'GA',
            'event_id' => $eventId,
            'product_category_id' => $categoryId,
            'product_type' => 'TICKET',
            'type' => 'PAID',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOccurrence(int $eventId): int
    {
        return DB::table('event_occurrences')->insertGetId([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => now()->addDay(),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertOrder(int $widgetId, OrderStatus $status, string $currency, float $totalGross, float $totalRefunded = 0): void
    {
        DB::table('orders')->insert([
            'short_id' => 'o_'.uniqid(),
            'public_id' => 'O-'.uniqid(),
            'event_id' => $this->eventId,
            'widget_id' => $widgetId,
            'status' => $status->name,
            'currency' => $currency,
            'total_gross' => $totalGross,
            'total_refunded' => $totalRefunded,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
