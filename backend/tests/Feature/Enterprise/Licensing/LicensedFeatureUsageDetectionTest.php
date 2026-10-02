<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Licensing;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\Licensing\LicensedFeature;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatMapRepositoryInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class LicensedFeatureUsageDetectionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['event_seat_maps', 'seat_maps', 'stripe_terminal_readers', 'box_offices'] as $table) {
            DB::table($table)->delete();
        }

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
    }

    public function test_an_install_with_nothing_set_up_uses_no_licensed_features(): void
    {
        $this->insertBoxOffice(isSystemDefault: true);

        $this->assertSame([], app(LicensedFeatureUsageService::class)->featuresInUse());
    }

    public function test_the_automatic_box_office_alone_is_not_box_office_use(): void
    {
        $this->insertBoxOffice(isSystemDefault: true);

        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_a_pin_on_the_automatic_box_office_is_box_office_use(): void
    {
        $this->insertBoxOffice(isSystemDefault: true, pinHash: 'hash');

        $this->assertTrue($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_an_organizer_created_box_office_is_box_office_use(): void
    {
        $this->insertBoxOffice(isSystemDefault: false);

        $this->assertTrue($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_a_door_sale_at_the_automatic_box_office_is_box_office_use(): void
    {
        $boxOfficeId = $this->insertBoxOffice(isSystemDefault: true);
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        DB::table('orders')->where('id', $orderId)->update(['box_office_id' => $boxOfficeId]);

        $this->assertTrue($this->boxOffices()->existsInUseForUpcomingEvent(null));

        DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_box_offices_that_are_deleted_or_on_deleted_events_do_not_count(): void
    {
        $boxOfficeId = $this->insertBoxOffice(isSystemDefault: false);
        DB::table('box_offices')->where('id', $boxOfficeId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent(null));

        $this->insertBoxOffice(isSystemDefault: false);
        DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_a_registered_card_reader_is_box_office_use_until_removed(): void
    {
        $readerId = DB::table('stripe_terminal_readers')->insertGetId([
            'organizer_id' => $this->organizerId,
            'stripe_reader_id' => 'tmr_'.uniqid(),
            'label' => 'Front door',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->readers()->existsForLiveOrganizer(null));
        $this->assertSame([LicensedFeature::BOX_OFFICE], app(LicensedFeatureUsageService::class)->featuresInUse());

        DB::table('stripe_terminal_readers')->where('id', $readerId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->readers()->existsForLiveOrganizer(null));
    }

    public function test_a_seat_map_is_seating_use_until_it_or_its_organizer_is_deleted(): void
    {
        $seatMapId = $this->insertSeatMap();

        $this->assertTrue($this->seatMaps()->existsForLiveOrganizer(null));
        $this->assertSame([LicensedFeature::SEATING], app(LicensedFeatureUsageService::class)->featuresInUse());

        DB::table('seat_maps')->where('id', $seatMapId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->seatMaps()->existsForLiveOrganizer(null));

        $this->insertSeatMap();
        DB::table('organizers')->where('id', $this->organizerId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->seatMaps()->existsForLiveOrganizer(null));
    }

    public function test_an_event_seat_map_is_seating_use_until_its_event_is_deleted(): void
    {
        DB::table('event_seat_maps')->insert([
            'event_id' => $this->eventId,
            'layout' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->eventSeatMaps()->existsForUpcomingEvent(null));

        DB::table('events')->where('id', $this->eventId)->update(['deleted_at' => now()]);

        $this->assertFalse($this->eventSeatMaps()->existsForUpcomingEvent(null));
    }

    public function test_a_box_office_on_an_event_whose_dates_have_all_passed_does_not_count(): void
    {
        $this->insertOccurrence(daysAhead: -3);
        $this->insertBoxOffice(isSystemDefault: false);

        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent(null));

        $this->insertOccurrence(daysAhead: 5);

        $this->assertTrue($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_a_cancelled_upcoming_date_does_not_keep_a_box_office_in_use(): void
    {
        $this->insertOccurrence(daysAhead: -3);
        $occurrenceId = $this->insertOccurrence(daysAhead: 5);
        DB::table('event_occurrences')->where('id', $occurrenceId)->update(['status' => EventOccurrenceStatus::CANCELLED->name]);
        $this->insertBoxOffice(isSystemDefault: false);

        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent(null));
    }

    public function test_an_event_seat_map_on_a_past_or_archived_event_does_not_count(): void
    {
        $this->insertOccurrence(daysAhead: 5);
        $this->insertEventSeatMapRow();

        $this->assertTrue($this->eventSeatMaps()->existsForUpcomingEvent(null));

        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::ARCHIVED->name]);

        $this->assertFalse($this->eventSeatMaps()->existsForUpcomingEvent(null));

        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_occurrences')->where('event_id', $this->eventId)->update([
            'start_date' => now()->subDays(2)->toDateTimeString(),
            'end_date' => now()->subDays(2)->addHours(2)->toDateTimeString(),
        ]);

        $this->assertFalse($this->eventSeatMaps()->existsForUpcomingEvent(null));
    }

    public function test_usage_is_scoped_to_the_account_that_set_it_up(): void
    {
        $this->insertBoxOffice(isSystemDefault: false);
        $this->insertSeatMap();
        $otherAccountId = $this->accountId + 100000;

        $this->assertTrue($this->boxOffices()->existsInUseForUpcomingEvent($this->accountId));
        $this->assertTrue($this->seatMaps()->existsForLiveOrganizer($this->accountId));
        $this->assertFalse($this->boxOffices()->existsInUseForUpcomingEvent($otherAccountId));
        $this->assertFalse($this->seatMaps()->existsForLiveOrganizer($otherAccountId));
        $this->assertSame([], app(LicensedFeatureUsageService::class)->featuresInUse($otherAccountId));
        $this->assertSame(
            [LicensedFeature::SEATING, LicensedFeature::BOX_OFFICE],
            app(LicensedFeatureUsageService::class)->featuresInUse($this->accountId),
        );
    }

    private function insertEventSeatMapRow(): void
    {
        DB::table('event_seat_maps')->insert([
            'event_id' => $this->eventId,
            'layout' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertBoxOffice(bool $isSystemDefault, ?string $pinHash = null): int
    {
        return DB::table('box_offices')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'bo_'.uniqid(),
            'name' => 'Box office',
            'is_system_default' => $isSystemDefault,
            'pin_hash' => $pinHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertSeatMap(): int
    {
        return DB::table('seat_maps')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Main hall',
            'layout' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function boxOffices(): BoxOfficeRepositoryInterface
    {
        return app(BoxOfficeRepositoryInterface::class);
    }

    private function readers(): StripeTerminalReaderRepositoryInterface
    {
        return app(StripeTerminalReaderRepositoryInterface::class);
    }

    private function seatMaps(): SeatMapRepositoryInterface
    {
        return app(SeatMapRepositoryInterface::class);
    }

    private function eventSeatMaps(): EventSeatMapRepositoryInterface
    {
        return app(EventSeatMapRepositoryInterface::class);
    }
}
