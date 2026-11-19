<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetAudienceOverviewHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetEventAudienceHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetLeadTimeHandler;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\GetRetentionHandler;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
    }

    public function test_the_overview_totals_buyers_repeat_rate_and_monthly_new_versus_returning(): void
    {
        $secondEventId = $this->createEvent($this->accountId, $this->organizerId, 'Second Event');
        $fourMonthsAgo = now()->startOfMonth()->subMonths(4)->addDays(9);
        $twoMonthsAgo = now()->startOfMonth()->subMonths(2)->addDays(9);

        $this->projectOrders(
            $this->buy('repeat@example.com', $this->eventId, $fourMonthsAgo, ['total_gross' => 100, 'opted_into_marketing_at' => $fourMonthsAgo]),
            $this->buy('repeat@example.com', $secondEventId, $twoMonthsAgo, ['total_gross' => 300]),
            $this->buy('once@example.com', $this->eventId, $twoMonthsAgo, ['total_gross' => 50, 'total_refunded' => 50]),
            $this->buy('fresh@example.com', $this->eventId, now(), ['total_gross' => 80, 'currency' => 'EUR'], tickets: 2),
            $this->insertOrder(['email' => 'quit@example.com', 'status' => 'ABANDONED', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]),
        );
        $this->projectWaitlistEntries($this->insertWaitlistEntry(['email' => 'wanda@example.com']));

        $overview = app(GetAudienceOverviewHandler::class)->handle($this->organizerId, true);

        $this->assertSame(5, $overview->contacts_count);
        $this->assertSame(1, $overview->mailable_count);
        $this->assertSame(3, $overview->buyers_count);
        $this->assertSame(1, $overview->repeat_buyers_count);
        $this->assertSame(33.3, $overview->repeat_rate);
        $this->assertSame(3, $overview->new_contacts_count);
        $this->assertSame(4, $overview->orders_count);
        $this->assertSame(5, $overview->tickets_count);
        $this->assertSame(1.33, $overview->average_events_per_buyer);

        $this->assertSame([
            ['month' => $fourMonthsAgo->format('Y-m'), 'new_buyers' => 1, 'returning_buyers' => 0],
            ['month' => $twoMonthsAgo->format('Y-m'), 'new_buyers' => 1, 'returning_buyers' => 1],
            ['month' => now()->format('Y-m'), 'new_buyers' => 1, 'returning_buyers' => 0],
        ], $overview->monthly_buyers);

        $this->assertSame([
            ['currency' => 'USD', 'total_net' => 400.0, 'top_decile_net' => 400.0, 'top_decile_share' => 100.0],
            ['currency' => 'EUR', 'total_net' => 80.0, 'top_decile_net' => 80.0, 'top_decile_share' => 100.0],
        ], array_map(static fn (array $row) => Arr::only($row, ['currency', 'total_net', 'top_decile_net', 'top_decile_share']), $overview->revenue_concentration));
    }

    public function test_the_overview_hides_revenue_concentration_without_financial_access(): void
    {
        $this->projectOrders($this->buy('repeat@example.com', $this->eventId, now()->subDays(3), ['total_gross' => 100]));

        $overview = app(GetAudienceOverviewHandler::class)->handle($this->organizerId, false);

        $this->assertNull($overview->revenue_concentration);
        $this->assertSame(1, $overview->buyers_count);
    }

    public function test_an_empty_audience_has_zeroed_totals(): void
    {
        $overview = app(GetAudienceOverviewHandler::class)->handle($this->organizerId, true);

        $this->assertSame([0, 0, 0.0, 0.0], [$overview->contacts_count, $overview->buyers_count, $overview->repeat_rate, $overview->average_events_per_buyer]);
        $this->assertSame([], $overview->monthly_buyers);
        $this->assertSame([], $overview->revenue_concentration);
    }

    public function test_the_overview_only_counts_this_organizers_audience(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $this->insertContact('outsider@example.com', ['orders_count' => 4, 'events_count' => 4, 'tickets_count' => 4], $otherOrganizerId);
        $this->projectOrders($this->buy('mine@example.com', $this->eventId, now()->subDays(3)));

        $overview = app(GetAudienceOverviewHandler::class)->handle($this->organizerId, true);

        $this->assertSame([1, 1, 1], [$overview->contacts_count, $overview->orders_count, $overview->tickets_count]);
    }

    public function test_retention_groups_buyers_by_first_month_and_tracks_who_came_back(): void
    {
        $cohortMonth = now()->startOfMonth()->subMonths(3);
        $this->projectOrders(
            $this->buy('loyal@example.com', $this->eventId, $cohortMonth->copy()->addDays(9)),
            $this->buy('loyal@example.com', $this->eventId, $cohortMonth->copy()->addDays(12)),
            $this->buy('loyal@example.com', $this->eventId, $cohortMonth->copy()->addMonth()->addDays(9)),
            $this->buy('loyal@example.com', $this->eventId, $cohortMonth->copy()->addMonths(2)->addDays(9)),
            $this->buy('once@example.com', $this->eventId, $cohortMonth->copy()->addDays(15)),
            $this->buy('late@example.com', $this->eventId, $cohortMonth->copy()->addMonths(2)->addDays(3)),
            $this->insertOrder(['email' => 'cancelled@example.com', 'status' => 'CANCELLED', 'created_at' => $cohortMonth->copy()->addDays(9), 'updated_at' => $cohortMonth->copy()->addDays(9)]),
            $this->buy('ancient@example.com', $this->eventId, now()->startOfMonth()->subMonths(14)->addDays(9)),
        );

        $retention = app(GetRetentionHandler::class)->handle($this->organizerId);

        $this->assertSame([
            [
                'cohort' => $cohortMonth->format('Y-m'),
                'size' => 2,
                'months' => [
                    ['offset' => 0, 'contacts' => 2, 'percent' => 100.0],
                    ['offset' => 1, 'contacts' => 1, 'percent' => 50.0],
                    ['offset' => 2, 'contacts' => 1, 'percent' => 50.0],
                ],
            ],
            [
                'cohort' => $cohortMonth->copy()->addMonths(2)->format('Y-m'),
                'size' => 1,
                'months' => [
                    ['offset' => 0, 'contacts' => 1, 'percent' => 100.0],
                ],
            ],
        ], $retention->cohorts);
        $this->assertSame(2, $retention->max_offset);
    }

    public function test_retention_months_follow_the_organizer_timezone(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'Pacific/Auckland']);
        $endOfMonthUtc = now()->startOfMonth()->subMonths(2)->subHour();

        $this->projectOrders($this->buy('kiwi@example.com', $this->eventId, $endOfMonthUtc));

        $retention = app(GetRetentionHandler::class)->handle($this->organizerId);

        $this->assertSame([$endOfMonthUtc->copy()->addHour()->format('Y-m')], array_column($retention->cohorts, 'cohort'));
    }

    public function test_an_event_audience_splits_new_and_returning_contacts_and_estimates_turnout(): void
    {
        [$firstPastEventId, $secondPastEventId] = $this->seedAttendanceHistory();
        $this->projectOrders(
            $this->buy('returning@example.com', $this->eventId, now()->subDays(5), ['opted_into_marketing_at' => now()->subDays(5)]),
            $this->buy('flaky@example.com', $this->eventId, now()->subDays(5)),
            $this->buy('new@example.com', $this->eventId, now()->subDays(5), tickets: 2),
        );

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);

        $this->assertSame($this->eventId, $audience->event_id);
        $this->assertSame(3, $audience->contacts_count);
        $this->assertSame(4, $audience->tickets_count);
        $this->assertSame(1, $audience->mailable_count);
        $this->assertSame(2, $audience->returning_count);
        $this->assertSame(1, $audience->new_count);
        $this->assertSame(0, $audience->checked_in_count);
        $this->assertSame(50.0, $audience->baseline_show_rate);
        $this->assertSame(2, $audience->expected_tickets);
        $this->assertSame(1, $audience->likely_no_show_contacts);
        $this->assertSame(['returning@example.com', 'flaky@example.com'], array_column($audience->top_contacts, 'email'));
        $this->assertSame(
            [
                ['event_id' => $secondPastEventId, 'title' => 'Past Event Two', 'shared_contacts' => 2],
                ['event_id' => $firstPastEventId, 'title' => 'Past Event One', 'shared_contacts' => 2],
            ],
            $audience->overlaps,
        );
    }

    public function test_turnout_is_estimated_from_the_history_of_this_events_ticket_holders_only(): void
    {
        $this->seedAttendanceHistory();
        $this->projectOrders($this->buy('flaky@example.com', $this->eventId, now()->subDays(5)));

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);

        $this->assertSame(0.0, $audience->baseline_show_rate);
        $this->assertSame(0, $audience->expected_tickets);
        $this->assertSame(1, $audience->likely_no_show_contacts);
    }

    public function test_the_checked_in_count_of_an_event_never_exceeds_its_tickets(): void
    {
        $pastOccurrenceId = $this->insertOccurrence($this->eventId, now()->subDays(3));
        $orderId = $this->insertOrder(['email' => 'pair@example.com']);
        $firstTicket = $this->insertAttendee($orderId, ['event_occurrence_id' => $pastOccurrenceId]);
        $secondTicket = $this->insertAttendee($orderId, ['event_occurrence_id' => $pastOccurrenceId]);
        $this->insertCheckIn($firstTicket);
        $unscannedOrderId = $this->buy('absent@example.com', $this->eventId, now()->subDays(5));
        $this->projectOrders($orderId, $unscannedOrderId);

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);
        $this->assertSame([3, 1], [$audience->tickets_count, $audience->checked_in_count]);

        $this->insertCheckIn($secondTicket);
        DB::table('attendees')->where('id', $secondTicket)->update(['status' => 'CANCELLED']);
        DB::table('attendees')->where('order_id', $unscannedOrderId)->update(['status' => 'CANCELLED']);
        $this->projectOrders($orderId, $unscannedOrderId);

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);
        $this->assertSame([1, 1], [$audience->tickets_count, $audience->checked_in_count]);
    }

    public function test_the_overview_counts_anyone_with_an_order_as_a_buyer_and_nobody_without_one(): void
    {
        $secondEventId = $this->createEvent($this->accountId, $this->organizerId, 'Second Event');
        $firstGift = $this->insertOrder(['email' => 'friend@example.com']);
        $secondGift = $this->insertOrder(['email' => 'friend@example.com', 'event_id' => $secondEventId]);
        $this->insertAttendee($firstGift, ['email' => 'guest@example.com', 'first_name' => 'Guest', 'last_name' => 'One']);
        $this->insertAttendee($secondGift, ['email' => 'guest@example.com', 'first_name' => 'Guest', 'last_name' => 'One']);
        $this->projectOrders(
            $this->insertOrder(['email' => 'no.ticket@example.com']),
            $firstGift,
            $secondGift,
            $this->buy('ticket@example.com', $this->eventId, now()->subDays(5)),
        );
        $this->insertContact('manual@example.com');

        $overview = app(GetAudienceOverviewHandler::class)->handle($this->organizerId, false);

        $this->assertSame([0, 2], [(int) $this->contact('guest@example.com')->orders_count, (int) $this->contact('guest@example.com')->events_count]);
        $this->assertSame(5, $overview->contacts_count);
        $this->assertSame(3, $overview->buyers_count);
        $this->assertSame(1, $overview->repeat_buyers_count);
        $this->assertSame(
            $overview->repeat_buyers_count,
            app(SegmentCountService::class)->count($this->organizerId, CrmSegmentPreset::REPEAT_BUYERS->definition())->contacts_count,
        );
    }

    public function test_someone_who_first_bought_this_event_and_another_one_later_is_new_to_it(): void
    {
        $laterEventId = $this->createEvent($this->accountId, $this->organizerId, 'Later Event');
        $this->projectOrders(
            $this->buy('early@example.com', $this->eventId, now()->subDays(9)),
            $this->buy('early@example.com', $laterEventId, now()->subDays(2)),
        );

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);

        $this->assertSame([1, 1, 0], [$audience->contacts_count, $audience->new_count, $audience->returning_count]);
    }

    public function test_orders_placed_in_the_same_second_count_the_earlier_order_as_booked_before(): void
    {
        $otherEventId = $this->createEvent($this->accountId, $this->organizerId, 'Other Event');
        $at = now()->subDays(3)->startOfSecond();
        $this->projectOrders(
            $this->buy('quick@example.com', $otherEventId, $at),
            $this->buy('quick@example.com', $this->eventId, $at),
        );

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);
        $otherAudience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $otherEventId);

        $this->assertSame([1, 0, 1], [$audience->contacts_count, $audience->new_count, $audience->returning_count]);
        $this->assertSame([1, 1, 0], [$otherAudience->contacts_count, $otherAudience->new_count, $otherAudience->returning_count]);
    }

    public function test_turnout_is_not_estimated_without_any_scanned_history(): void
    {
        $this->projectOrders($this->buy('new@example.com', $this->eventId, now()->subDays(5)));

        $audience = app(GetEventAudienceHandler::class)->handle($this->organizerId, $this->eventId);

        $this->assertSame(1, $audience->tickets_count);
        $this->assertNull($audience->baseline_show_rate);
        $this->assertNull($audience->expected_tickets);
        $this->assertSame(0, $audience->likely_no_show_contacts);
    }

    public function test_an_event_of_another_organizer_has_no_audience_here(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $otherEventId = $this->createEvent($this->accountId, $otherOrganizerId, 'Other Event');

        $this->expectException(ResourceNotFoundException::class);

        app(GetEventAudienceHandler::class)->handle($this->organizerId, $otherEventId);
    }

    public function test_lead_time_buckets_tickets_by_how_far_ahead_they_were_bought(): void
    {
        $startsAt = now()->addDays(10);
        $occurrenceId = $this->insertOccurrence($this->eventId, $startsAt);
        $this->projectOrders(
            $this->buy('same-day@example.com', $this->eventId, $startsAt->copy()->subHours(12), occurrenceId: $occurrenceId),
            $this->buy('week@example.com', $this->eventId, $startsAt->copy()->subDays(5), occurrenceId: $occurrenceId, tickets: 2),
            $this->buy('early@example.com', $this->eventId, $startsAt->copy()->subDays(45), occurrenceId: $occurrenceId),
            $this->buy('at-the-door@example.com', $this->eventId, $startsAt->copy()->addHour(), occurrenceId: $occurrenceId),
        );

        $leadTime = app(GetLeadTimeHandler::class)->handle($this->organizerId);

        $this->assertSame(4, $leadTime->tickets_count);
        $this->assertSame(
            [
                ['bucket' => 0, 'tickets' => 1, 'percent' => 25.0],
                ['bucket' => 1, 'tickets' => 0, 'percent' => 0.0],
                ['bucket' => 2, 'tickets' => 2, 'percent' => 50.0],
                ['bucket' => 3, 'tickets' => 0, 'percent' => 0.0],
                ['bucket' => 4, 'tickets' => 0, 'percent' => 0.0],
                ['bucket' => 5, 'tickets' => 1, 'percent' => 25.0],
                ['bucket' => 6, 'tickets' => 0, 'percent' => 0.0],
            ],
            $leadTime->buckets,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedAttendanceHistory(): array
    {
        $firstPastEventId = $this->createEvent($this->accountId, $this->organizerId, 'Past Event One');
        $secondPastEventId = $this->createEvent($this->accountId, $this->organizerId, 'Past Event Two');
        $firstOccurrenceId = $this->insertOccurrence($firstPastEventId, now()->subDays(40));
        $secondOccurrenceId = $this->insertOccurrence($secondPastEventId, now()->subDays(30));

        $this->projectOrders(
            $this->buy('returning@example.com', $firstPastEventId, now()->subDays(50), occurrenceId: $firstOccurrenceId, checkedIn: true),
            $this->buy('returning@example.com', $secondPastEventId, now()->subDays(50), occurrenceId: $secondOccurrenceId, checkedIn: true),
            $this->buy('flaky@example.com', $firstPastEventId, now()->subDays(50), occurrenceId: $firstOccurrenceId),
            $this->buy('flaky@example.com', $secondPastEventId, now()->subDays(50), occurrenceId: $secondOccurrenceId),
            $this->buy('elsewhere@example.com', $firstPastEventId, now()->subDays(50), occurrenceId: $firstOccurrenceId, checkedIn: true),
        );

        return [$firstPastEventId, $secondPastEventId];
    }

    private function buy(
        string $email,
        int $eventId,
        CarbonInterface $at,
        array $order = [],
        int $tickets = 1,
        ?int $occurrenceId = null,
        bool $checkedIn = false,
    ): int {
        $orderId = $this->insertOrder(['email' => $email, 'event_id' => $eventId, 'created_at' => $at, 'updated_at' => $at, ...$order]);

        for ($ticket = 0; $ticket < $tickets; $ticket++) {
            $attendeeId = $this->insertAttendee($orderId, $occurrenceId === null ? [] : ['event_occurrence_id' => $occurrenceId]);

            if ($checkedIn) {
                $this->insertCheckIn($attendeeId, ['created_at' => now()->subDays(29)]);
            }
        }

        return $orderId;
    }
}
