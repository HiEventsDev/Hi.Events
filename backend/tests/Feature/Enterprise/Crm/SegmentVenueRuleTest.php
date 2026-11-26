<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentReferenceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentResolver;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentVenueRuleTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private const DUBLIN_ONLY = 'dublin-only@example.com';

    private const CORK_ONLY = 'cork-only@example.com';

    private const BOTH = 'both@example.com';

    private int $dublinId;

    private int $corkId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();

        $tourId = $this->createEvent($this->accountId, $this->organizerId, 'Touring Show');
        $this->dublinId = $this->insertLocation('Dublin Hall');
        $this->corkId = $this->insertLocation('Cork Arena');

        DB::table('events')->where('id', $tourId)->update([
            'event_location_id' => $this->insertEventLocation($tourId, $this->dublinId),
        ]);

        $dublinDate = $this->insertOccurrence($tourId, now()->subDays(20));
        $corkDate = $this->insertOccurrence($tourId, now()->subDays(10));
        DB::table('event_occurrences')->where('id', $corkDate)->update([
            'event_location_id' => $this->insertEventLocation($tourId, $this->corkId),
        ]);

        $this->projectOrders(
            $this->ticketOrder(self::DUBLIN_ONLY, $tourId, $dublinDate),
            $this->ticketOrder(self::CORK_ONLY, $tourId, $corkDate),
            $this->ticketOrder(self::BOTH, $tourId, $dublinDate),
            $this->ticketOrder(self::BOTH, $tourId, $corkDate),
        );
    }

    public function test_a_date_with_its_own_venue_matches_only_the_people_ticketed_for_that_date(): void
    {
        $this->assertSame([self::BOTH, self::CORK_ONLY], $this->matching('in', [$this->corkId]));
        $this->assertSame([self::DUBLIN_ONLY], $this->matching('not_in', [$this->corkId]));
    }

    public function test_a_date_without_its_own_venue_uses_the_event_venue(): void
    {
        $this->assertSame([self::BOTH, self::DUBLIN_ONLY], $this->matching('in', [$this->dublinId]));
        $this->assertSame([self::CORK_ONLY], $this->matching('not_in', [$this->dublinId]));
    }

    public function test_either_venue_matches_everyone_ticketed_for_the_tour(): void
    {
        $this->assertSame(
            [self::BOTH, self::CORK_ONLY, self::DUBLIN_ONLY],
            $this->matching('in', [$this->dublinId, $this->corkId]),
        );
        $this->assertSame([], $this->matching('not_in', [$this->dublinId, $this->corkId]));
    }

    private function insertLocation(string $name): int
    {
        return DB::table('locations')->insertGetId([
            'short_id' => 'loc_'.uniqid(),
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEventLocation(int $eventId, int $locationId): int
    {
        return DB::table('event_locations')->insertGetId([
            'short_id' => 'el_'.uniqid(),
            'event_id' => $eventId,
            'location_id' => $locationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ticketOrder(string $email, int $eventId, int $occurrenceId): int
    {
        $orderId = $this->insertOrder(['email' => $email, 'event_id' => $eventId]);
        $this->insertAttendee($orderId, ['event_occurrence_id' => $occurrenceId]);

        return $orderId;
    }

    /**
     * @param  int[]  $locationIds
     * @return string[]
     */
    private function matching(string $operator, array $locationIds): array
    {
        $segment = app(SegmentResolver::class)->resolve(
            $this->organizerId,
            new SegmentReferenceDTO(definition: [
                'match' => 'all',
                'rules' => [['field' => 'ticket_location', 'operator' => $operator, 'value' => $locationIds]],
            ]),
            true,
        );

        $contacts = app(CrmContactRepositoryInterface::class)->findByOrganizerId(
            $this->organizerId,
            QueryParamsDTO::fromArray(['per_page' => 100, 'sort_by' => 'email', 'sort_direction' => 'asc']),
            $segment,
        );

        return collect($contacts->items())->map(static fn (CrmContactDomainObject $contact) => $contact->getEmail())->all();
    }
}
