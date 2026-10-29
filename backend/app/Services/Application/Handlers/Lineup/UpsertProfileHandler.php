<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\Enums\ProfileLinkType;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\Generated\EventLineupEntryDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProfileDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;
use HiEvents\Repository\Interfaces\ProfileRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertProfileDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class UpsertProfileHandler
{
    public function __construct(
        private readonly ProfileRepositoryInterface $profileRepository,
        private readonly EventLineupEntryRepositoryInterface $lineupEntryRepository,
        private readonly HtmlPurifierService $purifier,
        private readonly EventSpamCheckDispatchService $spamCheckDispatchService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(UpsertProfileDTO $dto): ProfileDomainObject
    {
        $attributes = [
            ProfileDomainObjectAbstract::NAME => trim($dto->name),
            ProfileDomainObjectAbstract::TAGLINE => $this->nullIfBlank($dto->tagline),
            ProfileDomainObjectAbstract::BIO => $this->nullIfBlank($this->purifier->purify($dto->bio)),
            ProfileDomainObjectAbstract::LINKS => ProfileLinkType::linksFromUrls($dto->links),
        ];

        if ($dto->profile_id === null) {
            $profileId = $this->profileRepository->create([
                ...$attributes,
                ProfileDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                ProfileDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            ])->getId();
        } else {
            $profileId = $this->findProfile($dto)->getId();
            $this->profileRepository->updateFromArray($profileId, $attributes);
            $this->recheckEventsShowing($profileId);
        }

        return $this->profileRepository
            ->loadRelation(new Relationship(ImageDomainObject::class))
            ->findById($profileId);
    }

    private function recheckEventsShowing(int $profileId): void
    {
        $this->lineupEntryRepository
            ->findWhere([EventLineupEntryDomainObjectAbstract::PROFILE_ID => $profileId])
            ->map(fn (EventLineupEntryDomainObject $entry) => $entry->getEventId())
            ->unique()
            ->each(fn (int $eventId) => $this->spamCheckDispatchService->dispatchForEvent($eventId));
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function findProfile(UpsertProfileDTO $dto): ProfileDomainObject
    {
        $profile = $this->profileRepository->findFirstWhere([
            ProfileDomainObjectAbstract::ID => $dto->profile_id,
            ProfileDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            ProfileDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
        ]);

        if ($profile === null) {
            throw new ResourceNotFoundException(__('Profile not found'));
        }

        return $profile;
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null || trim(strip_tags($value)) === '') {
            return null;
        }

        return trim($value);
    }
}
