<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmSegmentPreset;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SegmentQueryTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private const REGULAR = 'regular@example.com';

    private const FLAKY = 'flaky@example.com';

    private const HALF = 'half@example.com';

    private const UPCOMING = 'upcoming@example.com';

    private const WANDA = 'wanda@example.com';

    private const LUCKY = 'lucky@example.com';

    private const OTHER_DATE = 'other-date@example.com';

    private const QUIT = 'quit@example.com';

    private const RECOVERED = 'recovered@example.com';

    private const BOUGHT_THEN_QUIT = 'bought-then-quit@example.com';

    private const TEXTER = 'texter@example.com';

    private const FAN = 'fan@example.com';

    private const LAPSED = 'lapsed@example.com';

    private const EVERYONE = [
        self::BOUGHT_THEN_QUIT,
        self::FLAKY,
        self::HALF,
        self::LUCKY,
        self::OTHER_DATE,
        self::QUIT,
        self::RECOVERED,
        self::REGULAR,
        self::UPCOMING,
        self::WANDA,
    ];

    private int $scannedEventId;

    private int $secondScannedEventId;

    private int $unscannedEventId;

    private int $scannedProductId;

    private int $locationId;

    private int $vipTagId;

    private int $promoCodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
        $this->seedAudience();
    }

    public function test_no_show_count_only_counts_ended_dates_that_had_at_least_one_check_in(): void
    {
        $this->assertSame([self::FLAKY], $this->matching([['field' => 'no_show_count', 'operator' => 'gte', 'value' => 2]]));
        $this->assertSame([self::HALF], $this->matching([['field' => 'no_show_count', 'operator' => 'eq', 'value' => 1]]));
        $this->assertSame(
            array_values(array_diff(self::EVERYONE, [self::FLAKY, self::HALF])),
            $this->matching([['field' => 'no_show_count', 'operator' => 'lte', 'value' => 0]]),
        );
        $this->assertSame(
            array_values(array_diff(self::EVERYONE, [self::FLAKY])),
            $this->matching([['field' => 'no_show_count', 'operator' => 'lte', 'value' => 1]]),
        );
        $this->assertSame(self::EVERYONE, $this->matching([['field' => 'no_show_count', 'operator' => 'gte', 'value' => 0]]));
        $this->assertSame([], $this->matching([['field' => 'no_show_count', 'operator' => 'eq', 'value' => 3]]));
        $this->assertSame(2, app(CrmContactRepositoryInterface::class)->countNoShows($this->contactId(self::FLAKY), now()));
        $this->assertSame(0, app(CrmContactRepositoryInterface::class)->countNoShows($this->contactId(self::UPCOMING), now()));
    }

    public function test_a_guest_ticket_that_was_not_scanned_is_not_a_no_show_when_the_buyer_checked_in(): void
    {
        $orderId = $this->insertOrder(['email' => 'host@example.com', 'event_id' => $this->scannedEventId]);
        $own = $this->insertAttendee($orderId, ['event_occurrence_id' => $this->occurrenceOf($this->scannedEventId)]);
        $this->insertAttendee($orderId, ['email' => null, 'first_name' => 'Plus', 'last_name' => 'One', 'event_occurrence_id' => $this->occurrenceOf($this->scannedEventId)]);
        $this->insertCheckIn($own);
        $this->projectOrders($orderId);

        $this->assertNotContains('host@example.com', $this->matching([['field' => 'no_show_count', 'operator' => 'gte', 'value' => 1]]));
    }

    public function test_a_scanned_guest_ticket_does_not_hide_the_buyers_own_no_show(): void
    {
        $occurrenceId = $this->occurrenceOf($this->scannedEventId);
        $orderId = $this->insertOrder(['email' => 'host@example.com', 'event_id' => $this->scannedEventId]);
        $this->insertAttendee($orderId, ['event_occurrence_id' => $occurrenceId]);
        $this->insertCheckIn($this->insertAttendee($orderId, ['email' => null, 'first_name' => 'Plus', 'last_name' => 'One', 'event_occurrence_id' => $occurrenceId]));
        $this->projectOrders($orderId);

        $this->assertSame(1, app(CrmContactRepositoryInterface::class)->countNoShows($this->contactId('host@example.com'), now()));
        $this->assertContains('host@example.com', $this->matching([['field' => 'no_show_count', 'operator' => 'gte', 'value' => 1]]));
    }

    public function test_a_buyer_who_only_bought_for_a_guest_is_not_a_no_show(): void
    {
        $orderId = $this->insertOrder(['email' => 'host@example.com', 'event_id' => $this->scannedEventId]);
        $this->insertAttendee($orderId, ['email' => null, 'first_name' => 'Plus', 'last_name' => 'One', 'event_occurrence_id' => $this->occurrenceOf($this->scannedEventId)]);
        $this->projectOrders($orderId);

        $this->assertSame(0, app(CrmContactRepositoryInterface::class)->countNoShows($this->contactId('host@example.com'), now()));
        $this->assertNotContains('host@example.com', $this->matching([['field' => 'no_show_count', 'operator' => 'gte', 'value' => 1]]));
    }

    public function test_waitlist_missed_out_matches_people_who_joined_and_never_got_a_ticket_for_that_date(): void
    {
        $this->assertSame(
            [self::OTHER_DATE, self::WANDA],
            $this->matching([['field' => 'waitlist_missed_out', 'operator' => 'is_true', 'value' => null]]),
        );
        $this->assertSame(
            array_values(array_diff(self::EVERYONE, [self::OTHER_DATE, self::WANDA])),
            $this->matching([['field' => 'waitlist_missed_out', 'operator' => 'is_false', 'value' => null]]),
        );
    }

    public function test_abandoned_checkout_ignores_checkouts_that_were_later_completed_for_the_same_event(): void
    {
        $this->assertSame(
            [self::BOUGHT_THEN_QUIT, self::QUIT],
            $this->matching([['field' => 'abandoned_checkout', 'operator' => 'is_true', 'value' => null]]),
        );
        $this->assertSame(
            array_values(array_diff(self::EVERYONE, [self::BOUGHT_THEN_QUIT, self::QUIT])),
            $this->matching([['field' => 'abandoned_checkout', 'operator' => 'is_false', 'value' => null]]),
        );
    }

    public function test_abandoned_checkout_ignores_someone_who_ended_up_with_a_ticket_for_the_same_event(): void
    {
        $otherEventId = $this->createEvent($this->accountId, $this->organizerId, 'Other Event');
        $abandoned = ['status' => 'ABANDONED', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)];
        $later = ['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)];
        $giftOrderId = $this->insertOrder(['email' => 'friend@example.com', ...$later]);
        $this->insertAttendee($giftOrderId, ['email' => 'gifted@example.com', 'first_name' => 'Gift', 'last_name' => 'Ed']);
        $elsewhereOrderId = $this->insertOrder(['email' => 'friend@example.com', 'event_id' => $otherEventId, ...$later]);
        $this->insertAttendee($elsewhereOrderId, ['email' => 'gifted-elsewhere@example.com', 'first_name' => 'Else', 'last_name' => 'Where']);

        $this->projectOrders(
            $this->insertOrder(['email' => 'gifted@example.com', ...$abandoned]),
            $this->insertOrder(['email' => 'gifted-elsewhere@example.com', ...$abandoned]),
            $giftOrderId,
            $elsewhereOrderId,
        );

        $this->assertSame(
            [self::BOUGHT_THEN_QUIT, 'gifted-elsewhere@example.com', self::QUIT],
            $this->matching([['field' => 'abandoned_checkout', 'operator' => 'is_true', 'value' => null]]),
        );
    }

    public function test_ticket_event_and_checked_in_event_match_on_held_tickets_and_scans(): void
    {
        $this->assertSame(
            [self::FLAKY, self::HALF, self::REGULAR],
            $this->matching([['field' => 'ticket_event', 'operator' => 'in', 'value' => [$this->scannedEventId]]]),
        );
        $this->assertSame(
            [self::FLAKY],
            $this->matching([['field' => 'ticket_event', 'operator' => 'in', 'value' => [$this->unscannedEventId, 999_999_999]]]),
        );
        $this->assertSame(
            [self::HALF, self::REGULAR],
            $this->matching([['field' => 'checked_in_event', 'operator' => 'in', 'value' => [$this->scannedEventId]]]),
        );
        $this->assertSame(
            [self::FLAKY],
            $this->matching([
                ['field' => 'ticket_event', 'operator' => 'in', 'value' => [$this->scannedEventId, $this->secondScannedEventId]],
                ['field' => 'checked_in_event', 'operator' => 'not_in', 'value' => [$this->scannedEventId, $this->secondScannedEventId]],
            ]),
        );
    }

    public function test_ticket_product_and_ticket_location_match_through_the_held_ticket(): void
    {
        $this->assertSame(
            [self::FLAKY, self::HALF, self::REGULAR],
            $this->matching([['field' => 'ticket_product', 'operator' => 'in', 'value' => [$this->scannedProductId]]]),
        );
        $this->assertSame(
            [self::FLAKY, self::HALF, self::REGULAR],
            $this->matching([['field' => 'ticket_location', 'operator' => 'in', 'value' => [$this->locationId]]]),
        );
    }

    public function test_tag_rules_match_tagged_contacts_and_ignore_tags_of_another_organizer(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $foreignTagId = $this->insertTag('VIP', $otherOrganizerId);
        DB::table('crm_contact_tags')->insert(['contact_id' => $this->contactId(self::WANDA), 'tag_id' => $foreignTagId]);

        $this->assertSame(
            [self::FLAKY, self::REGULAR],
            $this->matching([['field' => 'tag', 'operator' => 'in', 'value' => [$this->vipTagId]]]),
        );
        $this->assertSame([], $this->matching([['field' => 'tag', 'operator' => 'in', 'value' => [$foreignTagId]]]));
        $this->assertSame(
            array_values(array_diff(self::EVERYONE, [self::FLAKY, self::REGULAR])),
            $this->matching([['field' => 'tag', 'operator' => 'not_in', 'value' => [$this->vipTagId]]]),
        );
    }

    public function test_counts_spend_dates_and_options_filter_on_the_rollups(): void
    {
        $this->assertSame([self::FLAKY], $this->matching([['field' => 'events_count', 'operator' => 'gte', 'value' => 3]]));
        $this->assertSame([self::FLAKY, self::HALF, self::REGULAR], $this->matching([['field' => 'orders_count', 'operator' => 'gte', 'value' => 2]]));
        $this->assertSame([self::HALF, self::REGULAR], $this->matching([['field' => 'attended_count', 'operator' => 'gte', 'value' => 1]]));
        $this->assertSame([self::FLAKY], $this->matching([['field' => 'total_spent', 'operator' => 'gte', 'value' => 150, 'currency' => 'USD']]));
        $this->assertSame([], $this->matching([['field' => 'total_spent', 'operator' => 'gte', 'value' => 1, 'currency' => 'EUR']]));
        $this->assertSame([self::WANDA], $this->matching([['field' => 'source', 'operator' => 'in', 'value' => ['WAITLIST']]]));
        $this->assertSame([self::LUCKY], $this->matching([['field' => 'email_marketing_status', 'operator' => 'in', 'value' => ['SUBSCRIBED']]]));
        $this->assertSame([self::UPCOMING], $this->matching([['field' => 'used_promo_code', 'operator' => 'is_true', 'value' => null]]));
        $this->assertSame([self::HALF, self::REGULAR], $this->matching([['field' => 'last_attended_at', 'operator' => 'within_days', 'value' => 30]]));
        $this->assertSame(
            [self::FLAKY, self::HALF, self::REGULAR],
            $this->matching([['field' => 'first_seen_at', 'operator' => 'before', 'value' => now()->subDays(50)->toDateString()]]),
        );
        $this->assertSame(
            [self::QUIT, self::WANDA],
            $this->matching([['field' => 'last_order_at', 'operator' => 'is_empty', 'value' => null]]),
        );
    }

    public function test_contacts_of_another_organizer_never_match(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $this->insertContact('outsider@example.com', ['orders_count' => 9, 'events_count' => 9], $otherOrganizerId);

        $this->assertSame([self::FLAKY], $this->matching([['field' => 'events_count', 'operator' => 'gte', 'value' => 3]]));
        $this->assertSame(self::EVERYONE, $this->matching([]));
    }

    public static function presetAudiences(): array
    {
        return [
            'subscribers' => [CrmSegmentPreset::SUBSCRIBERS, [self::LUCKY]],
            'sms subscribers' => [CrmSegmentPreset::SMS_SUBSCRIBERS, [self::TEXTER]],
            'first timers' => [CrmSegmentPreset::FIRST_TIMERS, [self::BOUGHT_THEN_QUIT, self::LUCKY, self::OTHER_DATE, self::RECOVERED, self::UPCOMING]],
            'repeat buyers' => [CrmSegmentPreset::REPEAT_BUYERS, [self::FAN, self::FLAKY, self::HALF, self::LAPSED, self::REGULAR]],
            'top fans' => [CrmSegmentPreset::TOP_FANS, [self::FAN]],
            'lapsed' => [CrmSegmentPreset::LAPSED, [self::LAPSED]],
            'no shows' => [CrmSegmentPreset::NO_SHOWS, [self::FLAKY]],
            'missed out' => [CrmSegmentPreset::MISSED_OUT, [self::OTHER_DATE, self::WANDA]],
            'abandoned checkout' => [CrmSegmentPreset::ABANDONED_CHECKOUT, [self::BOUGHT_THEN_QUIT, self::QUIT]],
        ];
    }

    #[DataProvider('presetAudiences')]
    public function test_every_preset_matches_exactly_the_contacts_it_describes(CrmSegmentPreset $preset, array $expected): void
    {
        $this->insertContact(self::TEXTER, ['phone' => '+353851234567', 'phone_country' => 'IE', 'sms_marketing_status' => 'SUBSCRIBED']);
        $this->insertContact(self::FAN, ['events_count' => 4, 'orders_count' => 4, 'last_order_at' => now()->subDays(5)]);
        $this->insertContact(self::LAPSED, ['events_count' => 2, 'orders_count' => 2, 'last_order_at' => now()->subDays(200)]);

        $this->assertSame($expected, $this->emailsFor(new SegmentReferenceDTO(preset: $preset->value)));

        $count = app(SegmentCountService::class)->count($this->organizerId, $preset->definition());
        $this->assertSame(count($expected), $count->contacts_count);
        $this->assertSame(count(array_intersect($expected, [self::LUCKY])), $count->mailable_count);
    }

    public function test_the_preset_list_covers_every_preset(): void
    {
        $this->assertSameSize(CrmSegmentPreset::cases(), self::presetAudiences());
    }

    public function test_a_saved_segment_resolves_by_id_only_for_its_own_organizer(): void
    {
        $otherOrganizerId = $this->createOrganizer($this->accountId, 'Other Organizer');
        $definition = ['match' => 'all', 'rules' => [['field' => 'events_count', 'operator' => 'gte', 'value' => 3]]];
        $mine = $this->insertSegment($this->organizerId, $definition);
        $theirs = $this->insertSegment($otherOrganizerId, $definition);

        $this->assertSame([self::FLAKY], $this->emailsFor(new SegmentReferenceDTO(segment_id: $mine)));

        $this->expectExceptionMessage('Segment not found');
        $this->emailsFor(new SegmentReferenceDTO(segment_id: $theirs));
    }

    public function test_a_segment_combines_with_search_and_sorting(): void
    {
        $segment = app(SegmentResolver::class)->resolve(
            $this->organizerId,
            new SegmentReferenceDTO(definition: ['rules' => [['field' => 'orders_count', 'operator' => 'gte', 'value' => 2]]]),
            true,
        );

        $sorted = app(CrmContactRepositoryInterface::class)->findByOrganizerId(
            $this->organizerId,
            QueryParamsDTO::fromArray(['sort_by' => 'events_count', 'sort_direction' => 'desc']),
            $segment,
        );
        $searched = app(CrmContactRepositoryInterface::class)->findByOrganizerId(
            $this->organizerId,
            QueryParamsDTO::fromArray(['query' => 'FLAK']),
            $segment,
        );
        $wildcard = app(CrmContactRepositoryInterface::class)->findByOrganizerId(
            $this->organizerId,
            QueryParamsDTO::fromArray(['query' => '%']),
            null,
        );

        $this->assertSame(self::FLAKY, collect($sorted->items())->first()->getEmail());
        $this->assertSame(3, $sorted->total());
        $this->assertSame([self::FLAKY], collect($searched->items())->map(static fn (CrmContactDomainObject $contact) => $contact->getEmail())->all());
        $this->assertSame(0, $wildcard->total());
    }

    private function seedAudience(): void
    {
        $this->scannedEventId = $this->createEvent($this->accountId, $this->organizerId, 'Scanned Event');
        $this->secondScannedEventId = $this->createEvent($this->accountId, $this->organizerId, 'Second Scanned Event');
        $this->unscannedEventId = $this->createEvent($this->accountId, $this->organizerId, 'Unscanned Event');
        $scanned = $this->insertOccurrence($this->scannedEventId, now()->subDays(20));
        $secondScanned = $this->insertOccurrence($this->secondScannedEventId, now()->subDays(25));
        $unscanned = $this->insertOccurrence($this->unscannedEventId, now()->subDays(40));
        $otherDate = $this->insertOccurrence($this->eventId, now()->addDays(17));
        [$this->scannedProductId] = $this->insertTicket($this->scannedEventId);

        $this->locationId = DB::table('locations')->insertGetId([
            'short_id' => 'loc_'.uniqid(),
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'The Venue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('events')->where('id', $this->scannedEventId)->update([
            'event_location_id' => DB::table('event_locations')->insertGetId([
                'short_id' => 'el_'.uniqid(),
                'event_id' => $this->scannedEventId,
                'location_id' => $this->locationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ]);
        $this->promoCodeId = DB::table('promo_codes')->insertGetId([
            'code' => 'early',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $past = ['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)];
        $orders = [];

        foreach ([self::REGULAR => [true, true], self::HALF => [true, false], self::FLAKY => [false, false]] as $email => [$firstScan, $secondScan]) {
            $orders[] = $this->ticketOrder($email, $this->scannedEventId, $scanned, $past, $firstScan, $this->scannedProductId);
            $orders[] = $this->ticketOrder($email, $this->secondScannedEventId, $secondScanned, $past, $secondScan);
        }
        $orders[] = $this->ticketOrder(self::FLAKY, $this->unscannedEventId, $unscanned, $past);
        $orders[] = $this->ticketOrder(self::UPCOMING, $this->eventId, $this->occurrenceId, ['promo_code_id' => $this->promoCodeId, 'promo_code' => 'early']);
        $orders[] = $this->ticketOrder(self::LUCKY, $this->eventId, $this->occurrenceId, ['opted_into_marketing_at' => now()->subDays(2)]);
        $orders[] = $this->ticketOrder(self::OTHER_DATE, $this->eventId, $otherDate);

        $orders[] = $this->insertOrder(['email' => self::QUIT, 'status' => 'ABANDONED']);
        $orders[] = $this->insertOrder(['email' => self::RECOVERED, 'status' => 'ABANDONED', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        $orders[] = $this->insertOrder(['email' => self::RECOVERED, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        $orders[] = $this->insertOrder(['email' => self::BOUGHT_THEN_QUIT, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        $orders[] = $this->insertOrder(['email' => self::BOUGHT_THEN_QUIT, 'status' => 'ABANDONED', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);

        $this->projectOrders(...$orders);
        $this->projectWaitlistEntries(
            $this->insertWaitlistEntry(['email' => self::WANDA]),
            $this->insertWaitlistEntry(['email' => self::LUCKY]),
            $this->insertWaitlistEntry(['email' => self::OTHER_DATE]),
        );

        $this->vipTagId = $this->insertTag('VIP');
        DB::table('crm_contact_tags')->insert([
            ['contact_id' => $this->contactId(self::REGULAR), 'tag_id' => $this->vipTagId],
            ['contact_id' => $this->contactId(self::FLAKY), 'tag_id' => $this->vipTagId],
        ]);
    }

    private function ticketOrder(string $email, int $eventId, int $occurrenceId, array $order = [], bool $checkedIn = false, ?int $productId = null): int
    {
        $orderId = $this->insertOrder(['email' => $email, 'event_id' => $eventId, ...$order]);
        $attendeeId = $this->insertAttendee($orderId, [
            'event_occurrence_id' => $occurrenceId,
            ...($productId === null ? [] : ['product_id' => $productId]),
        ]);

        if ($checkedIn) {
            $this->insertCheckIn($attendeeId, ['created_at' => now()->subDays(20)]);
        }

        return $orderId;
    }

    private function occurrenceOf(int $eventId): int
    {
        return (int) DB::table('event_occurrences')->where('event_id', $eventId)->value('id');
    }

    private function insertSegment(int $organizerId, array $definition): int
    {
        return DB::table('crm_segments')->insertGetId([
            'account_id' => $this->accountId,
            'organizer_id' => $organizerId,
            'name' => 'Segment '.uniqid(),
            'definition' => json_encode($definition),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return string[]
     */
    private function matching(array $rules, string $match = 'all'): array
    {
        return $this->emailsFor(new SegmentReferenceDTO(definition: ['match' => $match, 'rules' => $rules]));
    }

    /**
     * @return string[]
     */
    private function emailsFor(SegmentReferenceDTO $reference): array
    {
        $segment = app(SegmentResolver::class)->resolve($this->organizerId, $reference, true);

        $contacts = app(CrmContactRepositoryInterface::class)->findByOrganizerId(
            $this->organizerId,
            QueryParamsDTO::fromArray(['per_page' => 100, 'sort_by' => 'email', 'sort_direction' => 'asc']),
            $segment,
        );

        return collect($contacts->items())->map(static fn (CrmContactDomainObject $contact) => $contact->getEmail())->all();
    }
}
