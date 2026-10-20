<?php

namespace Tests\Unit\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertProfileDTO;
use HiEvents\Services\Application\Handlers\Lineup\UpsertProfileHandler;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class UpsertProfileHandlerTest extends TestCase
{
    private ProfileRepositoryInterface $profileRepository;

    private HtmlPurifierService $purifier;

    private EventSpamCheckDispatchService $spamCheckDispatchService;

    private EventLineupEntryRepositoryInterface $lineupEntryRepository;

    private UpsertProfileHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileRepository = m::mock(ProfileRepositoryInterface::class);
        $this->purifier = m::mock(HtmlPurifierService::class);
        $this->spamCheckDispatchService = m::mock(EventSpamCheckDispatchService::class);
        $this->lineupEntryRepository = m::mock(EventLineupEntryRepositoryInterface::class);

        $this->handler = new UpsertProfileHandler(
            $this->profileRepository,
            $this->lineupEntryRepository,
            $this->purifier,
            $this->spamCheckDispatchService,
        );
    }

    public function test_creates_profile_with_purified_bio_and_typed_links(): void
    {
        $this->purifier->shouldReceive('purify')->once()->with('<p>Bio<script></script></p>')->andReturn('<p>Bio</p>');

        $this->profileRepository->shouldReceive('create')->once()->with([
            'name' => 'Nova Lights',
            'tagline' => null,
            'bio' => '<p>Bio</p>',
            'links' => [
                ['type' => 'SPOTIFY', 'url' => 'https://open.spotify.com/artist/1'],
                ['type' => 'WEBSITE', 'url' => 'https://nova.example'],
            ],
            'account_id' => 1,
            'organizer_id' => 2,
        ])->andReturn((new ProfileDomainObject)->setId(10));

        $this->spamCheckDispatchService->shouldNotReceive('dispatchForEvent');

        $profile = (new ProfileDomainObject)->setId(10);
        $this->profileRepository->shouldReceive('loadRelation')->once()->andReturnSelf();
        $this->profileRepository->shouldReceive('findById')->once()->with(10)->andReturn($profile);

        $result = $this->handler->handle(new UpsertProfileDTO(
            account_id: 1,
            organizer_id: 2,
            name: '  Nova Lights ',
            tagline: '   ',
            bio: '<p>Bio<script></script></p>',
            links: ['https://open.spotify.com/artist/1', ' https://nova.example ', 'https://nova.example', ''],
        ));

        $this->assertSame($profile, $result);
    }

    public function test_updating_a_profile_rechecks_only_the_events_it_appears_on(): void
    {
        $this->purifier->shouldReceive('purify')->andReturn(null);
        $this->profileRepository->shouldReceive('findFirstWhere')->once()->andReturn((new ProfileDomainObject)->setId(5));
        $this->profileRepository->shouldReceive('updateFromArray')->once();
        $this->lineupEntryRepository->shouldReceive('findWhere')->once()->with(['profile_id' => 5])->andReturn(new Collection([
            (new EventLineupEntryDomainObject)->setEventId(7),
            (new EventLineupEntryDomainObject)->setEventId(9),
        ]));
        $this->spamCheckDispatchService->shouldReceive('dispatchForEvent')->once()->with(7);
        $this->spamCheckDispatchService->shouldReceive('dispatchForEvent')->once()->with(9);
        $this->spamCheckDispatchService->shouldNotReceive('dispatchForOrganizer');
        $this->profileRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->profileRepository->shouldReceive('findById')->andReturn((new ProfileDomainObject)->setId(5));

        $this->handler->handle(new UpsertProfileDTO(account_id: 1, organizer_id: 2, name: 'Nova', profile_id: 5));
    }

    public function test_update_of_profile_outside_organizer_is_not_found(): void
    {
        $this->purifier->shouldReceive('purify')->andReturn(null);
        $this->profileRepository->shouldReceive('findFirstWhere')->once()->with([
            'id' => 5,
            'organizer_id' => 2,
            'account_id' => 1,
        ])->andReturnNull();
        $this->profileRepository->shouldNotReceive('updateFromArray');
        $this->spamCheckDispatchService->shouldNotReceive('dispatchForEvent');

        $this->expectException(ResourceNotFoundException::class);

        $this->handler->handle(new UpsertProfileDTO(
            account_id: 1,
            organizer_id: 2,
            name: 'Nova',
            profile_id: 5,
        ));
    }
}
