<?php

namespace Tests\Feature\Http\Actions\SeatMaps;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapCloneService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapLayoutValidator;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Event\DuplicateEventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class EventSeatMapTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private string $authToken;

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
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, true);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public function test_attach_copies_the_venue_map_as_a_snapshot(): void
    {
        $seatMapId = $this->createSeatMap('theatre');

        $attach = $this->postJson($this->url(), ['seat_map_id' => $seatMapId], $this->authHeaders());

        $attach->assertStatus(ResponseCodes::HTTP_CREATED)
            ->assertJsonPath('data.source_seat_map.id', $seatMapId)
            ->assertJsonPath('data.is_update_available_from_source', false)
            ->assertJsonPath('data.layout.areas.0.name', 'Stalls')
            ->assertJsonPath('data.band_products', []);

        $this->postJson($this->url(), ['seat_map_id' => $seatMapId], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_attach_and_rules_accept_numeric_strings_from_form_controls(): void
    {
        $seatMapId = $this->createSeatMap('empty');

        $this->postJson($this->url(), ['seat_map_id' => (string) $seatMapId], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CREATED);

        $this->putJson($this->url('/rules'), ['prevent_orphan_seats' => true, 'max_seats_per_order' => '6', 'allow_seat_change' => true], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.prevent_orphan_seats', true)
            ->assertJsonPath('data.max_seats_per_order', 6)
            ->assertJsonPath('data.allow_seat_change', true);
    }

    public function test_a_seat_map_from_another_organizer_cannot_be_attached(): void
    {
        $otherOrganizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId, 'name' => 'Other', 'email' => uniqid().'@example.test',
            'currency' => 'USD', 'timezone' => 'UTC', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $seatMapId = $this->createSeatMap('empty', $otherOrganizerId);

        $this->postJson($this->url(), ['seat_map_id' => $seatMapId], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_event_without_a_seat_map_returns_not_found(): void
    {
        $this->getJson($this->url(), $this->authHeaders())->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_bands_can_be_linked_to_ticket_products_only(): void
    {
        $this->attach('theatre');
        $adult = $this->insertProduct();
        $child = $this->insertProduct();
        $merch = $this->insertProduct(ProductType::GENERAL->name);
        $donation = $this->insertProduct(ProductType::TICKET->name, 'DONATION');

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $adult], ['product_id' => $child]]],
            ['band_key' => 'b_standard', 'products' => [['product_id' => $adult]]],
        ]], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.band_products.0.band_key', 'b_premium')
            ->assertJsonPath('data.band_products.0.products', [
                ['product_id' => $adult, 'price_adjustment' => 0],
                ['product_id' => $child, 'price_adjustment' => 0],
            ])
            ->assertJsonPath('data.band_products.1.products', [['product_id' => $adult, 'price_adjustment' => 0]]);

        foreach ([$merch, $donation] as $unlinkable) {
            $this->putJson($this->url('/band-products'), ['band_products' => [
                ['band_key' => 'b_premium', 'products' => [['product_id' => $unlinkable]]],
            ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_unknown', 'products' => [['product_id' => $adult]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(3, DB::table('event_seat_map_band_products')->count());
    }

    public function test_layout_update_bumps_the_version_and_cannot_remove_a_linked_band(): void
    {
        $this->attach('conference');
        $product = $this->insertProduct();
        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_value', 'products' => [['product_id' => $product]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $layout = $this->fixture('conference');
        $layout['areas'][0]['name'] = 'Great hall';
        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.layout.areas.0.name', 'Great hall');

        $layout['bands'] = array_values(array_filter($layout['bands'], fn (array $band) => $band['key'] !== 'b_value'));
        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);
    }

    public function test_sync_from_source_reports_a_diff_then_applies_it(): void
    {
        $seatMapId = $this->attach('conference');

        $edited = $this->fixture('conference');
        array_pop($edited['areas'][0]['elements'][1]['seats']);
        $this->putJson("/organizers/{$this->organizerId}/seat-maps/$seatMapId", [
            'name' => 'Conference', 'layout' => $edited,
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $this->getJson($this->url(), $this->authHeaders())
            ->assertJsonPath('data.is_update_available_from_source', true);

        $this->postJson($this->url('/sync-from-source'), ['dry_run' => true], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.added_seat_count', 0)
            ->assertJsonPath('data.removed_seat_labels', ['Ballroom · E-8']);

        $this->assertSame(1, DB::table('event_seat_maps')->where('event_id', $this->eventId)->value('version'));

        $this->postJson($this->url('/sync-from-source'), ['dry_run' => false], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->getJson($this->url(), $this->authHeaders())
            ->assertJsonPath('data.is_update_available_from_source', false)
            ->assertJsonPath('data.version', 2);
    }

    public function test_detach_removes_the_snapshot_and_its_links(): void
    {
        $this->attach('theatre');
        $product = $this->insertProduct();
        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product]]],
        ]], $this->authHeaders());

        $this->deleteJson($this->url(), [], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_NO_CONTENT);

        $this->assertSame(0, DB::table('event_seat_maps')->where('event_id', $this->eventId)->count());
        $this->assertSame(0, DB::table('event_seat_map_band_products')->count());
    }

    public function test_clone_remaps_band_links_to_the_duplicated_products(): void
    {
        $this->attach('theatre');
        $product = $this->insertProduct();
        $unclonedProduct = $this->insertProduct();
        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product, 'price_adjustment' => 1500], ['product_id' => $unclonedProduct]]],
        ]], $this->authHeaders());

        DB::table('event_seat_maps')->where('event_id', $this->eventId)
            ->update(['prevent_orphan_seats' => true, 'max_seats_per_order' => 6, 'allow_seat_change' => true]);

        $sourceEventId = $this->eventId;
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $newProduct = $this->insertProduct();

        app(EventSeatMapCloneService::class)->clone($sourceEventId, $this->eventId, [$product => $newProduct]);

        $clone = DB::table('event_seat_maps')->where('event_id', $this->eventId)->first();
        $cloneId = $clone->id;
        $this->assertTrue($clone->prevent_orphan_seats);
        $this->assertSame(6, $clone->max_seats_per_order);
        $this->assertTrue($clone->allow_seat_change);
        $this->assertSame(
            [['band_key' => 'b_premium', 'product_id' => $newProduct, 'price_adjustment' => 1500]],
            DB::table('event_seat_map_band_products')->where('event_seat_map_id', $cloneId)
                ->get(['band_key', 'product_id', 'price_adjustment'])->map(fn ($row) => (array) $row)->all(),
        );
    }

    public function test_duplicating_an_event_copies_its_seat_map_while_seating_is_on_for_the_account(): void
    {
        $this->useCloudLicence();
        $this->attach('theatre');
        DB::table('organizer_settings')->insert(['organizer_id' => $this->organizerId, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::find($this->userId));

        $copyId = $this->duplicate();

        $this->assertSame(1, DB::table('event_seat_maps')->where('event_id', $copyId)->count());
    }

    public function test_reading_an_event_without_a_seat_map_is_a_plain_not_found_even_with_seating_off(): void
    {
        $this->useCloudLicence();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, false);

        $this->getJson("/events/{$this->eventId}/seat-map", $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_NOT_FOUND);
    }

    public function test_a_seated_event_is_never_duplicated_into_unseated_tickets_when_seating_is_off(): void
    {
        $this->useCloudLicence();
        $this->attach('theatre');
        DB::table('organizer_settings')->insert(['organizer_id' => $this->organizerId, 'created_at' => now(), 'updated_at' => now()]);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::SEATING, false);
        $eventCount = DB::table('events')->count();

        $this->postJson("/events/{$this->eventId}/duplicate", [
            'title' => 'Copy',
            'start_date' => now()->addDays(10)->toDateTimeString(),
            'duplicate_products' => true,
            'duplicate_questions' => false,
            'duplicate_settings' => false,
            'duplicate_promo_codes' => false,
            'duplicate_capacity_assignments' => false,
            'duplicate_check_in_lists' => false,
            'duplicate_event_cover_image' => false,
            'duplicate_webhooks' => false,
            'duplicate_affiliates' => false,
            'duplicate_ticket_logo' => false,
        ], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJsonPath('message', 'This event uses a seat map, but reserved seating is not available on your account. Duplicate it without products, or enable reserved seating first.');

        $this->assertSame($eventCount, DB::table('events')->count());

        $this->actingAs(User::find($this->userId));
        $copyId = $this->duplicate(duplicateProducts: false);

        $this->assertSame(0, DB::table('event_seat_maps')->where('event_id', $copyId)->count());
    }

    public function test_seats_sold_on_a_date_that_ended_within_the_last_day_still_freeze_the_map(): void
    {
        $this->attach('conference');
        $endedOccurrenceId = $this->insertOccurrence();
        DB::table('event_occurrences')->where('id', $endedOccurrenceId)->update([
            'start_date' => now()->subHours(3),
            'end_date' => now()->subHour(),
        ]);
        $this->insertClaim($endedOccurrenceId, $this->insertOrder(OrderStatus::COMPLETED->name), 'e2.0.0', 'Ballroom · A-1', 'b_premium');

        $layout = $this->fixture('conference');
        array_shift($layout['areas'][0]['elements'][1]['seats']);

        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);

        DB::table('event_occurrences')->where('id', $endedOccurrenceId)->update([
            'start_date' => now()->subHours(27),
            'end_date' => now()->subHours(25),
        ]);

        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_a_sold_seat_cannot_be_removed_or_rebanded_but_other_edits_are_allowed(): void
    {
        $this->attach('conference');
        $occurrenceId = $this->insertOccurrence();
        $this->insertClaim($occurrenceId, $this->insertOrder(OrderStatus::COMPLETED->name), 'e2.0.0', 'Ballroom · A-1', 'b_premium');

        $removed = $this->fixture('conference');
        array_shift($removed['areas'][0]['elements'][1]['seats']);
        $this->putJson($this->url('/layout'), ['layout' => $removed], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJsonFragment(['message' => 'These seats are sold or held on an upcoming date and cannot be removed or moved to another band: Ballroom · A-1']);

        $rebanded = $this->fixture('conference');
        $rebanded['areas'][0]['elements'][1]['overrides']['0.0'] = ['band' => 'b_value'];
        $rebanded['areas'][0]['elements'][1]['seats'][0]['band'] = 'b_value';
        $this->putJson($this->url('/layout'), ['layout' => $rebanded], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);

        $otherSeatRemoved = $this->fixture('conference');
        array_pop($otherSeatRemoved['areas'][0]['elements'][1]['seats']);
        $this->putJson($this->url('/layout'), ['layout' => $otherSeatRemoved], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_renaming_an_area_with_sold_seats_needs_confirmation_then_renames_the_tickets(): void
    {
        $this->attach('conference');
        $occurrenceId = $this->insertOccurrence();
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $this->insertClaim($occurrenceId, $orderId, 'e2.0.0', 'Ballroom · A-1', 'b_premium');
        $attendeeId = $this->insertAttendeeForClaim($orderId, 'e2.0.0', 'Ballroom · A-1');

        $renamed = $this->fixture('conference');
        $renamed['areas'][0]['name'] = 'Great hall';

        $this->putJson($this->url('/layout'), ['layout' => $renamed], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJson([
                'requires_relabel_confirmation' => true,
                'relabelled_seat_labels' => ['Ballroom · A-1'],
            ]);

        $this->assertSame('Ballroom · A-1', DB::table('seat_claims')->where('order_id', $orderId)->value('seat_label'));

        $this->putJson($this->url('/layout'), ['layout' => $renamed, 'confirm_relabel' => true], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertSame('Great hall · A-1', DB::table('seat_claims')->where('order_id', $orderId)->value('seat_label'));
        $this->assertSame('Great hall · A-1', DB::table('attendees')->where('id', $attendeeId)->value('seat_label'));
    }

    public function test_a_stale_version_is_rejected_so_two_editors_cannot_overwrite_each_other(): void
    {
        $this->attach('conference');
        $layout = $this->fixture('conference');

        $this->putJson($this->url('/layout'), ['layout' => $layout, 'version' => 1], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->putJson($this->url('/layout'), ['layout' => $layout, 'version' => 1], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);

        $this->putJson($this->url('/layout'), ['layout' => $layout, 'version' => 2], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_seats_sold_only_on_a_past_date_do_not_freeze_the_map(): void
    {
        $this->attach('conference');
        $pastOccurrenceId = $this->insertOccurrence(daysAhead: -3);
        $this->insertClaim($pastOccurrenceId, $this->insertOrder(OrderStatus::COMPLETED->name), 'e2.0.0', 'Ballroom · A-1', 'b_premium');

        $layout = $this->fixture('conference');
        array_shift($layout['areas'][0]['elements'][1]['seats']);

        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_removing_a_seat_purges_its_expired_claim_so_a_late_payment_cannot_revive_it(): void
    {
        $this->attach('conference');
        $occurrenceId = $this->insertOccurrence();
        $expiredOrderId = $this->insertOrder(OrderStatus::RESERVED->name, now()->subMinutes(3));
        $this->insertClaim($occurrenceId, $expiredOrderId, 'e2.0.0', 'Ballroom · A-1', 'b_premium');

        $layout = $this->fixture('conference');
        array_shift($layout['areas'][0]['elements'][1]['seats']);
        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertSame(0, DB::table('seat_claims')->where('order_id', $expiredOrderId)->count());
    }

    public function test_a_zone_cannot_shrink_below_what_is_already_sold(): void
    {
        $this->attach('club');
        $occurrenceId = $this->insertOccurrence();
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        foreach (range(1, 3) as $ignored) {
            $this->insertClaim($occurrenceId, $orderId, 'z2', 'Main floor · Front pit', 'b_premium', isZone: true);
        }

        $layout = $this->fixture('club');
        $layout['areas'][0]['elements'][1]['capacity'] = 2;
        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);

        $layout['areas'][0]['elements'][1]['capacity'] = 3;
        $this->putJson($this->url('/layout'), ['layout' => $layout], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_a_seat_map_with_sold_seats_cannot_be_detached(): void
    {
        $this->attach('conference');
        $this->insertClaim($this->insertOccurrence(), $this->insertOrder(OrderStatus::COMPLETED->name), 'e2.0.0', 'Ballroom · A-1', 'b_premium');

        $this->deleteJson($this->url(), [], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_CONFLICT);

        $this->assertSame(1, DB::table('event_seat_maps')->where('event_id', $this->eventId)->count());
    }

    public function test_a_ticket_with_sold_seats_cannot_be_unlinked_from_their_band(): void
    {
        $this->attach('conference');
        $product = $this->insertProduct();
        $otherProduct = $this->insertProduct();
        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);
        $this->insertClaim($this->insertOccurrence(), $this->insertOrder(OrderStatus::COMPLETED->name), 'e2.0.0', 'Ballroom · A-1', 'b_premium', productId: $product);

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_value', 'products' => [['product_id' => $product]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        $this->putJson($this->url('/band-products'), ['band_products' => []], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product], ['product_id' => $otherProduct]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_a_ticket_with_seatless_sales_cannot_be_linked_to_seats(): void
    {
        $this->attach('conference');
        $occurrenceId = $this->insertOccurrence();
        $sold = $this->insertProduct();
        $reserved = $this->insertProduct();
        $expired = $this->insertProduct();
        $cancelled = $this->insertProduct();
        $this->sellTickets($sold, $this->insertPrice($sold, null), $occurrenceId, 1);
        $this->reserve($reserved, $this->insertPrice($reserved, null), $occurrenceId, 1, reservedUntil: now()->addMinutes(10));
        $this->reserve($expired, $this->insertPrice($expired, null), $occurrenceId, 1, reservedUntil: now()->subMinute());
        $this->sellTickets($cancelled, $this->insertPrice($cancelled, null), $occurrenceId, 1, attendeeStatus: AttendeeStatus::CANCELLED->name);

        foreach ([$sold, $reserved] as $productId) {
            $this->putJson($this->url('/band-products'), ['band_products' => [
                ['band_key' => 'b_premium', 'products' => [['product_id' => $productId]]],
            ]], $this->authHeaders())
                ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
                ->assertJsonPath('errors.band_products.0', fn (string $message) => str_contains($message, 'without seats'));
        }

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $expired], ['product_id' => $cancelled]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $expired], ['product_id' => $cancelled, 'price_adjustment' => 500]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertSame(0, DB::table('event_seat_map_band_products')->whereIn('product_id', [$sold, $reserved])->count());
    }

    public function test_a_band_listed_twice_or_too_many_bands_is_a_validation_error(): void
    {
        $this->attach('theatre');
        $adult = $this->insertProduct();

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $adult]]],
            ['band_key' => 'b_premium', 'products' => []],
        ]], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('band_products.1.band_key');

        $this->putJson($this->url('/band-products'), ['band_products' => array_map(
            fn (int $band) => ['band_key' => "b_$band", 'products' => []],
            range(0, SeatMapLayoutValidator::MAX_BANDS),
        )], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('band_products');

        $this->assertSame(0, DB::table('event_seat_map_band_products')->count());
    }

    public function test_a_null_price_adjustment_is_a_validation_error(): void
    {
        $this->attach('conference');
        $product = $this->insertProduct();

        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product, 'price_adjustment' => null]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_seated_ticket_cannot_become_a_donation(): void
    {
        $this->attach('conference');
        $product = $this->insertProduct();
        $this->insertPrice($product, null);
        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $this->putJson("/events/{$this->eventId}/products/$product", [
            'title' => 'Seated',
            'type' => 'DONATION',
            'product_type' => ProductType::TICKET->name,
            'product_category_id' => 1,
            'prices' => [['price' => 10]],
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame('PAID', DB::table('products')->where('id', $product)->value('type'));
    }

    public function test_a_seated_ticket_made_free_loses_its_band_adjustments(): void
    {
        $this->attach('conference');
        $product = $this->insertProduct();
        $this->insertPrice($product, null);
        $categoryId = DB::table('product_categories')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Tickets',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $product)->update(['product_category_id' => $categoryId]);
        $this->putJson($this->url('/band-products'), ['band_products' => [
            ['band_key' => 'b_premium', 'products' => [['product_id' => $product, 'price_adjustment' => 1500]]],
        ]], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $this->putJson("/events/{$this->eventId}/products/$product", [
            'title' => 'Seated',
            'type' => 'FREE',
            'product_type' => ProductType::TICKET->name,
            'product_category_id' => $categoryId,
            'prices' => [['price' => 0]],
        ], $this->authHeaders())->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertSame(0, DB::table('event_seat_map_band_products')->where('product_id', $product)->value('price_adjustment'));
    }

    private function insertClaim(int $occurrenceId, int $orderId, string $seatUid, string $label, string $bandKey, bool $isZone = false, ?int $productId = null): void
    {
        DB::table('seat_claims')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'seat_uid' => $seatUid,
            'is_zone' => $isZone,
            'band_key' => $bandKey,
            'seat_label' => $label,
            'order_id' => $orderId,
            'product_id' => $productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAttendeeForClaim(int $orderId, string $seatUid, string $seatLabel): int
    {
        $productId = $this->insertProduct();

        $attendeeId = DB::table('attendees')->insertGetId([
            'product_id' => $productId,
            'product_price_id' => $this->insertPrice($productId, null),
            'short_id' => 'att_'.uniqid(),
            'email' => 'attendee+'.uniqid().'@example.test',
            'order_id' => $orderId,
            'event_id' => $this->eventId,
            'public_id' => 'ATT_'.uniqid(),
            'status' => 'ACTIVE',
            'seat_uid' => $seatUid,
            'seat_label' => $seatLabel,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('seat_claims')
            ->where('order_id', $orderId)
            ->where('seat_uid', $seatUid)
            ->update(['attendee_id' => $attendeeId]);

        return $attendeeId;
    }

    private function attach(string $template): int
    {
        $seatMapId = $this->createSeatMap($template);
        $this->postJson($this->url(), ['seat_map_id' => $seatMapId], $this->authHeaders())
            ->assertStatus(ResponseCodes::HTTP_CREATED);

        return $seatMapId;
    }

    private function createSeatMap(string $template, ?int $organizerId = null): int
    {
        return DB::table('seat_maps')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId ?? $this->organizerId,
            'name' => ucfirst($template),
            'layout' => json_encode($this->fixture($template)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function url(string $suffix = ''): string
    {
        return "/events/{$this->eventId}/seat-map$suffix";
    }

    private function fixture(string $template): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/seating/$template.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }

    private function duplicate(bool $duplicateProducts = true): int
    {
        return app(DuplicateEventService::class)->duplicateEvent(
            eventId: (string) $this->eventId,
            accountId: (string) $this->accountId,
            title: 'Copy',
            startDate: now()->addDays(10)->toDateTimeString(),
            duplicateProducts: $duplicateProducts,
            duplicateEventCoverImage: false,
            duplicateTicketLogo: false,
            duplicateWebhooks: false,
        )->getId();
    }
}
