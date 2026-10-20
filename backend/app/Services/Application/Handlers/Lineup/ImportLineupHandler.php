<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\LineupBillingTier;
use HiEvents\DomainObjects\Enums\ProfileLinkType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventScheduleItemPerformerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemPerformerRepositoryInterface;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupDTO;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupPersonDTO;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupResultDTO;
use HiEvents\Services\Application\Handlers\Lineup\DTO\ImportLineupSlotDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ImportLineupHandler
{
    private const MAX_LINKS = 12;

    private const PERFORMER_INSERT_CHUNK = 500;

    private EventDomainObject $event;

    private string $timezone;

    /** @var Collection<string, ProfileDomainObject> */
    private Collection $profilesByName;

    /** @var Collection<int, EventLineupEntryDomainObject> */
    private Collection $entriesByProfileId;

    /** @var Collection<string, EventSpaceDomainObject> */
    private Collection $spacesByName;

    /** @var array<string, true> */
    private array $slotKeys;

    private array $performerRows;

    /** @var int[] */
    private array $updatedProfileIds;

    private int $nextSortOrder;

    private array $counts;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly EventSpaceRepositoryInterface $spaceRepository,
        private readonly EventScheduleItemRepositoryInterface $scheduleItemRepository,
        private readonly EventScheduleItemPerformerRepositoryInterface $performerRepository,
        private readonly HtmlPurifierService $purifier,
        private readonly EventSpamCheckDispatchService $spamCheckDispatchService,
        private readonly DatabaseManager $databaseManager,
        private readonly TransactionLockService $transactionLockService,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(ImportLineupDTO $dto): ImportLineupResultDTO
    {
        $result = $this->databaseManager->transaction(function () use ($dto) {
            $this->load($dto->event_id);

            foreach ($dto->people as $person) {
                $this->importPerson($person);
            }

            foreach ($dto->slots as $slot) {
                $this->importSlot($slot);
            }

            foreach (array_chunk($this->performerRows, self::PERFORMER_INSERT_CHUNK) as $rows) {
                $this->performerRepository->insert($rows);
            }

            return new ImportLineupResultDTO(...$this->counts);
        });

        $this->spamCheckDispatchService->dispatchForEvent($dto->event_id);

        if ($this->updatedProfileIds !== []) {
            $this->lineupEntryRepository
                ->findWhereIn(EventLineupEntryDomainObjectAbstract::PROFILE_ID, $this->updatedProfileIds)
                ->map(fn (EventLineupEntryDomainObject $entry) => $entry->getEventId())
                ->unique()
                ->reject(fn (int $eventId) => $eventId === $dto->event_id)
                ->each(fn (int $eventId) => $this->spamCheckDispatchService->dispatchForEvent($eventId));
        }

        return $result;
    }

    private function load(int $eventId): void
    {
        $this->transactionLockService->lockEventLineup($eventId);
        $this->event = $this->eventRepository->findById($eventId);
        $this->timezone = $this->event->getTimezone() ?? 'UTC';
        $this->performerRows = [];
        $this->updatedProfileIds = [];
        $this->counts = [
            'people_created' => 0,
            'people_updated' => 0,
            'people_added' => 0,
            'stages_created' => 0,
            'slots_created' => 0,
            'slots_skipped' => 0,
        ];

        $this->entriesByProfileId = $this->lineupEntryRepository
            ->findWhere([EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId])
            ->keyBy(fn (EventLineupEntryDomainObject $entry) => $entry->getProfileId());

        $this->nextSortOrder = ($this->entriesByProfileId->max(
            fn (EventLineupEntryDomainObject $entry) => $entry->getSortOrder()
        ) ?? -1) + 1;

        $this->profilesByName = collect();
        $this->profileRepository
            ->findWhere(
                where: [
                    ProfileDomainObjectAbstract::ORGANIZER_ID => $this->event->getOrganizerId(),
                    ProfileDomainObjectAbstract::ACCOUNT_ID => $this->event->getAccountId(),
                ],
                orderAndDirections: [new OrderAndDirection(ProfileDomainObjectAbstract::ID)],
            )
            ->each(function (ProfileDomainObject $profile) {
                $key = $this->key($profile->getName());
                $current = $this->profilesByName->get($key);

                if ($current === null || (! $this->entriesByProfileId->has($current->getId()) && $this->entriesByProfileId->has($profile->getId()))) {
                    $this->profilesByName->put($key, $profile);
                }
            });

        $this->spacesByName = collect();
        $this->spaceRepository
            ->findWhere(
                where: [EventSpaceDomainObjectAbstract::EVENT_ID => $eventId],
                orderAndDirections: [new OrderAndDirection(EventSpaceDomainObjectAbstract::ID)],
            )
            ->each(function (EventSpaceDomainObject $space) {
                if (! $this->spacesByName->has($this->key($space->getName()))) {
                    $this->spacesByName->put($this->key($space->getName()), $space);
                }
            });

        $this->slotKeys = $this->scheduleItemRepository
            ->loadRelation(new Relationship(EventScheduleItemPerformerDomainObject::class, name: 'performers'))
            ->findWhere([EventScheduleItemDomainObjectAbstract::EVENT_ID => $eventId])
            ->mapWithKeys(fn (EventScheduleItemDomainObject $item) => [
                $this->slotKey(
                    CarbonImmutable::parse($item->getStartsAt(), 'UTC'),
                    CarbonImmutable::parse($item->getEndsAt(), 'UTC'),
                    $item->getEventSpaceId(),
                    $item->getTitle(),
                    ($item->getPerformers() ?? collect())
                        ->map(fn (EventScheduleItemPerformerDomainObject $performer) => $performer->getEventLineupEntryId())
                        ->all(),
                ) => true,
            ])
            ->all();
    }

    private function importPerson(ImportLineupPersonDTO $person): void
    {
        [$profile, $profileChanged] = $this->saveProfile($person);
        $entry = $this->entriesByProfileId->get($profile->getId());

        if ($entry === null) {
            $this->addEntry($profile, $person->billing_tier, $person->role_label, $person->is_hidden ?? false);
            $this->counts['people_updated'] += $profileChanged ? 1 : 0;

            return;
        }

        $changes = array_filter([
            EventLineupEntryDomainObjectAbstract::BILLING_TIER => $person->billing_tier !== null && $person->billing_tier->value !== $entry->getBillingTier()
                ? $person->billing_tier->value
                : null,
            EventLineupEntryDomainObjectAbstract::ROLE_LABEL => $person->role_label !== null && $person->role_label !== $entry->getRoleLabel()
                ? $person->role_label
                : null,
        ], fn ($value) => $value !== null);

        if ($person->is_hidden !== null && $person->is_hidden !== $entry->getIsHidden()) {
            $changes[EventLineupEntryDomainObjectAbstract::IS_HIDDEN] = $person->is_hidden;
        }

        if ($changes !== []) {
            $this->entriesByProfileId->put($profile->getId(), $this->lineupEntryRepository->updateFromArray($entry->getId(), $changes));
        }

        if ($profileChanged || $changes !== []) {
            $this->counts['people_updated']++;
        }
    }

    /**
     * @return array{0: ProfileDomainObject, 1: bool}
     */
    private function saveProfile(ImportLineupPersonDTO $person): array
    {
        $existing = $this->profilesByName->get($this->key($person->name));

        if ($existing === null) {
            $this->counts['people_created']++;

            return [$this->rememberProfile($this->profileRepository->create([
                ProfileDomainObjectAbstract::ACCOUNT_ID => $this->event->getAccountId(),
                ProfileDomainObjectAbstract::ORGANIZER_ID => $this->event->getOrganizerId(),
                ProfileDomainObjectAbstract::NAME => $person->name,
                ProfileDomainObjectAbstract::TAGLINE => $person->tagline,
                ProfileDomainObjectAbstract::BIO => $this->purifiedOrNull($person->bio),
                ProfileDomainObjectAbstract::LINKS => ProfileLinkType::linksFromUrls(array_slice($person->links, 0, self::MAX_LINKS)),
            ])), false];
        }

        $existingUrls = collect($existing->getLinks() ?? [])->pluck('url')->values();
        $urls = $existingUrls->merge($person->links)->unique()->take(self::MAX_LINKS)->values();

        $changes = [];

        if ($person->tagline !== null && $person->tagline !== $existing->getTagline()) {
            $changes[ProfileDomainObjectAbstract::TAGLINE] = $person->tagline;
        }

        if ($person->bio !== null && $this->plainText($person->bio) !== $this->plainText($existing->getBio())) {
            $changes[ProfileDomainObjectAbstract::BIO] = $this->purifiedOrNull($person->bio);
        }

        if ($urls->count() !== $existingUrls->count()) {
            $changes[ProfileDomainObjectAbstract::LINKS] = ProfileLinkType::linksFromUrls($urls->all());
        }

        if ($changes === []) {
            return [$existing, false];
        }

        $this->updatedProfileIds[] = $existing->getId();

        return [$this->rememberProfile($this->profileRepository->updateFromArray($existing->getId(), $changes)), true];
    }

    private function rememberProfile(ProfileDomainObject $profile): ProfileDomainObject
    {
        $this->profilesByName->put($this->key($profile->getName()), $profile);

        return $profile;
    }

    private function addEntry(ProfileDomainObject $profile, ?LineupBillingTier $tier, ?string $roleLabel, bool $isHidden): EventLineupEntryDomainObject
    {
        $entry = $this->lineupEntryRepository->create([
            EventLineupEntryDomainObjectAbstract::EVENT_ID => $this->event->getId(),
            EventLineupEntryDomainObjectAbstract::PROFILE_ID => $profile->getId(),
            EventLineupEntryDomainObjectAbstract::BILLING_TIER => ($tier ?? LineupBillingTier::STANDARD)->value,
            EventLineupEntryDomainObjectAbstract::ROLE_LABEL => $roleLabel,
            EventLineupEntryDomainObjectAbstract::IS_HIDDEN => $isHidden,
            EventLineupEntryDomainObjectAbstract::SORT_ORDER => $this->nextSortOrder++,
        ]);

        $this->counts['people_added']++;
        $this->entriesByProfileId->put($profile->getId(), $entry);

        return $entry;
    }

    private function importSlot(ImportLineupSlotDTO $slot): void
    {
        $entryIds = collect($slot->people)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => $this->key($name))
            ->map(fn (string $name) => $this->entryForName($name)->getId())
            ->values()
            ->all();
        $spaceId = $this->spaceIdForName($slot->stage);
        $startsAt = CarbonImmutable::parse($slot->starts_at, $this->timezone)->utc();
        $endsAt = CarbonImmutable::parse($slot->ends_at, $this->timezone)->utc();
        $key = $this->slotKey($startsAt, $endsAt, $spaceId, $slot->title, $entryIds);

        if (isset($this->slotKeys[$key])) {
            $this->counts['slots_skipped']++;

            return;
        }

        $item = $this->scheduleItemRepository->create([
            EventScheduleItemDomainObjectAbstract::EVENT_ID => $this->event->getId(),
            EventScheduleItemDomainObjectAbstract::EVENT_SPACE_ID => $spaceId,
            EventScheduleItemDomainObjectAbstract::TITLE => $slot->title,
            EventScheduleItemDomainObjectAbstract::DESCRIPTION => $this->purifiedOrNull($slot->description),
            EventScheduleItemDomainObjectAbstract::STARTS_AT => $startsAt->format('Y-m-d H:i:s'),
            EventScheduleItemDomainObjectAbstract::ENDS_AT => $endsAt->format('Y-m-d H:i:s'),
        ]);

        foreach ($entryIds as $position => $entryId) {
            $this->performerRows[] = [
                EventScheduleItemPerformerDomainObjectAbstract::EVENT_SCHEDULE_ITEM_ID => $item->getId(),
                EventScheduleItemPerformerDomainObjectAbstract::EVENT_LINEUP_ENTRY_ID => $entryId,
                EventScheduleItemPerformerDomainObjectAbstract::SORT_ORDER => $position,
            ];
        }

        $this->slotKeys[$key] = true;
        $this->counts['slots_created']++;
    }

    private function entryForName(string $name): EventLineupEntryDomainObject
    {
        $profile = $this->profilesByName->get($this->key($name))
            ?? $this->saveProfile(new ImportLineupPersonDTO(name: $name))[0];

        return $this->entriesByProfileId->get($profile->getId())
            ?? $this->addEntry($profile, null, null, false);
    }

    private function spaceIdForName(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $key = $this->key($name);

        if (! $this->spacesByName->has($key)) {
            $this->spacesByName->put($key, $this->spaceRepository->create([
                EventSpaceDomainObjectAbstract::EVENT_ID => $this->event->getId(),
                EventSpaceDomainObjectAbstract::NAME => trim($name),
            ]));
            $this->counts['stages_created']++;
        }

        return $this->spacesByName->get($key)->getId();
    }

    /**
     * @param  int[]  $entryIds
     */
    private function slotKey(CarbonImmutable $start, CarbonImmutable $end, ?int $spaceId, ?string $title, array $entryIds): string
    {
        sort($entryIds);

        return implode('|', [
            $start->format('Y-m-d H:i'),
            $end->format('Y-m-d H:i'),
            $spaceId ?? '-',
            $this->key($title ?? ''),
            implode(',', $entryIds),
        ]);
    }

    private function purifiedOrNull(?string $html): ?string
    {
        $purified = $this->purifier->purify($html);

        return $purified === null || trim(strip_tags($purified)) === '' ? null : $purified;
    }

    private function plainText(?string $html): string
    {
        return $this->key(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/li)[^>]*>/i', ' ', $html ?? '')), ENT_QUOTES | ENT_HTML5));
    }

    private function key(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}
