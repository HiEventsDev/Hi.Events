<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertLineupEntryDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use Illuminate\Database\UniqueConstraintViolationException;

class UpsertLineupEntryHandler
{
    public function __construct(
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSpamCheckDispatchService $spamCheckDispatchService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function handle(UpsertLineupEntryDTO $dto): EventLineupEntryDomainObject
    {
        $attributes = [
            EventLineupEntryDomainObjectAbstract::BILLING_TIER => $dto->billing_tier->value,
            EventLineupEntryDomainObjectAbstract::ROLE_LABEL => $dto->role_label === null || trim($dto->role_label) === ''
                ? null
                : trim($dto->role_label),
        ];

        if ($dto->is_hidden !== null) {
            $attributes[EventLineupEntryDomainObjectAbstract::IS_HIDDEN] = $dto->is_hidden;
        }

        if ($dto->lineup_entry_id === null) {
            $entryId = $this->create($dto, $attributes);
        } else {
            $entryId = $this->findEntry($dto->event_id, $dto->lineup_entry_id)->getId();
            $this->lineupEntryRepository->updateFromArray($entryId, $attributes);
        }

        $this->spamCheckDispatchService->dispatchForEvent($dto->event_id);

        return $this->lineupEntryRepository
            ->loadRelation(new Relationship(ProfileDomainObject::class, nested: [
                new Relationship(ImageDomainObject::class),
            ], name: 'profile'))
            ->findById($entryId);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    private function create(UpsertLineupEntryDTO $dto, array $attributes): int
    {
        $event = $this->eventRepository->findById($dto->event_id);

        $profile = $this->profileRepository->findFirstWhere([
            ProfileDomainObjectAbstract::ID => $dto->profile_id,
            ProfileDomainObjectAbstract::ORGANIZER_ID => $event->getOrganizerId(),
            ProfileDomainObjectAbstract::ACCOUNT_ID => $event->getAccountId(),
        ]);

        if ($profile === null) {
            throw new ResourceNotFoundException(__('Profile not found'));
        }

        $existing = $this->lineupEntryRepository->findFirstWhere([
            EventLineupEntryDomainObjectAbstract::EVENT_ID => $dto->event_id,
            EventLineupEntryDomainObjectAbstract::PROFILE_ID => $profile->getId(),
        ]);

        if ($existing !== null) {
            throw new ResourceConflictException(__('This profile is already on the lineup'));
        }

        $lastEntry = $this->lineupEntryRepository->findWhere(
            where: [EventLineupEntryDomainObjectAbstract::EVENT_ID => $dto->event_id],
            orderAndDirections: [new OrderAndDirection(
                EventLineupEntryDomainObjectAbstract::SORT_ORDER,
                OrderAndDirection::DIRECTION_DESC,
            )],
            limit: 1,
        )->first();

        try {
            return $this->lineupEntryRepository->create([
                ...$attributes,
                EventLineupEntryDomainObjectAbstract::IS_HIDDEN => $dto->is_hidden ?? false,
                EventLineupEntryDomainObjectAbstract::EVENT_ID => $dto->event_id,
                EventLineupEntryDomainObjectAbstract::PROFILE_ID => $profile->getId(),
                EventLineupEntryDomainObjectAbstract::SORT_ORDER => ($lastEntry?->getSortOrder() ?? -1) + 1,
            ])->getId();
        } catch (UniqueConstraintViolationException) {
            throw new ResourceConflictException(__('This profile is already on the lineup'));
        }
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function findEntry(int $eventId, int $entryId): EventLineupEntryDomainObject
    {
        $entry = $this->lineupEntryRepository->findFirstWhere([
            EventLineupEntryDomainObjectAbstract::ID => $entryId,
            EventLineupEntryDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($entry === null) {
            throw new ResourceNotFoundException(__('Lineup entry not found'));
        }

        return $entry;
    }
}
