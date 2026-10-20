<?php

namespace Tests\Unit\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertLineupEntryDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertLineupEntryHandler;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class UpsertLineupEntryHandlerTest extends TestCase
{
    private EventLineupEntryRepositoryInterface $lineupEntryRepository;

    private ProfileRepositoryInterface $profileRepository;

    private EventRepositoryInterface $eventRepository;

    private EventSpamCheckDispatchService $spamCheckDispatchService;

    private UpsertLineupEntryHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lineupEntryRepository = m::mock(EventLineupEntryRepositoryInterface::class);
        $this->profileRepository = m::mock(ProfileRepositoryInterface::class);
        $this->eventRepository = m::mock(EventRepositoryInterface::class);
        $this->spamCheckDispatchService = m::mock(EventSpamCheckDispatchService::class);

        $this->handler = new UpsertLineupEntryHandler(
            $this->lineupEntryRepository,
            $this->profileRepository,
            $this->eventRepository,
            $this->spamCheckDispatchService,
        );

        $this->eventRepository->shouldReceive('findById')->with(7)->andReturn(
            (new EventDomainObject)->setId(7)->setOrganizerId(3)->setAccountId(1)
        );
    }

    public function test_profile_must_belong_to_the_events_organizer(): void
    {
        $this->profileRepository->shouldReceive('findFirstWhere')->once()->with([
            'id' => 99,
            'organizer_id' => 3,
            'account_id' => 1,
        ])->andReturnNull();
        $this->lineupEntryRepository->shouldNotReceive('create');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle($this->dto(profileId: 99));
    }

    public function test_profile_cannot_be_added_twice(): void
    {
        $this->profileRepository->shouldReceive('findFirstWhere')->andReturn((new ProfileDomainObject)->setId(4));
        $this->lineupEntryRepository->shouldReceive('findFirstWhere')->once()->with([
            'event_id' => 7,
            'profile_id' => 4,
        ])->andReturn(new EventLineupEntryDomainObject);
        $this->lineupEntryRepository->shouldNotReceive('create');

        $this->expectException(ResourceConflictException::class);

        $this->handler->handle($this->dto(profileId: 4));
    }

    public function test_new_entries_are_appended_after_the_last_entry(): void
    {
        $this->profileRepository->shouldReceive('findFirstWhere')->andReturn((new ProfileDomainObject)->setId(4));
        $this->lineupEntryRepository->shouldReceive('findFirstWhere')->andReturnNull();
        $this->lineupEntryRepository->shouldReceive('findWhere')->once()->andReturn(new Collection([
            (new EventLineupEntryDomainObject)->setSortOrder(5),
        ]));
        $this->lineupEntryRepository->shouldReceive('create')->once()->with([
            'billing_tier' => 'HEADLINER',
            'role_label' => 'Closing set',
            'is_hidden' => false,
            'event_id' => 7,
            'profile_id' => 4,
            'sort_order' => 6,
        ])->andReturn((new EventLineupEntryDomainObject)->setId(12));
        $this->spamCheckDispatchService->shouldReceive('dispatchForEvent')->once()->with(7);
        $this->lineupEntryRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->lineupEntryRepository->shouldReceive('findById')->with(12)->andReturn((new EventLineupEntryDomainObject)->setId(12));

        $this->assertSame(12, $this->handler->handle($this->dto(profileId: 4, roleLabel: ' Closing set '))->getId());
    }

    private function dto(int $profileId, ?string $roleLabel = null): UpsertLineupEntryDTO
    {
        return new UpsertLineupEntryDTO(
            event_id: 7,
            billing_tier: LineupBillingTier::HEADLINER,
            role_label: $roleLabel,
            profile_id: $profileId,
        );
    }
}
