<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class UpdateLocationHandler
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly LocationDataSanitizer $sanitizer,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function handle(int $locationId, UpsertLocationDTO $dto): LocationDomainObject
    {
        try {
            return $this->databaseManager->transaction(fn () => $this->update($locationId, $dto));
        } catch (UniqueConstraintViolationException $exception) {
            throw new ResourceConflictException(__('Another saved location already uses this place.'), previous: $exception);
        }
    }

    private function update(int $locationId, UpsertLocationDTO $dto): LocationDomainObject
    {
        $location = $this->locationRepository->findFirstWhere([
            LocationDomainObjectAbstract::ID => $locationId,
            LocationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            LocationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
        ]);

        if ($location === null) {
            throw new ResourceNotFoundException(__('Location not found'));
        }

        $this->guardAgainstProviderPlaceConflict($location, $dto);

        return $this->locationRepository->updateFromArray($location->getId(), [
            LocationDomainObjectAbstract::NAME => $this->sanitizer->sanitizeText($dto->name),
            LocationDomainObjectAbstract::STRUCTURED_ADDRESS => $this->sanitizer->sanitizeAddress($dto->structured_address->toArray()),
            LocationDomainObjectAbstract::LATITUDE => $dto->latitude,
            LocationDomainObjectAbstract::LONGITUDE => $dto->longitude,
            LocationDomainObjectAbstract::PROVIDER => $dto->provider,
            LocationDomainObjectAbstract::PROVIDER_PLACE_ID => $dto->provider_place_id,
            LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE => $this->resolveRawProviderResponse($location, $dto),
        ]);
    }

    private function guardAgainstProviderPlaceConflict(LocationDomainObject $location, UpsertLocationDTO $dto): void
    {
        if ($dto->provider === null || $dto->provider_place_id === null) {
            return;
        }

        $existing = $this->locationRepository->findFirstWhere([
            LocationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            LocationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            LocationDomainObjectAbstract::PROVIDER => $dto->provider,
            LocationDomainObjectAbstract::PROVIDER_PLACE_ID => $dto->provider_place_id,
        ]);

        if ($existing !== null && $existing->getId() !== $location->getId()) {
            throw new ResourceConflictException(__('Another saved location already uses this place.'));
        }
    }

    private function resolveRawProviderResponse(LocationDomainObject $location, UpsertLocationDTO $dto): array|string|null
    {
        $cached = $this->sanitizer->cachedRawProviderResponse($dto->provider, $dto->provider_place_id);

        if ($cached !== null) {
            return $cached;
        }

        $placeUnchanged = $dto->provider === $location->getProvider()
            && $dto->provider_place_id === $location->getProviderPlaceId();

        return $placeUnchanged ? $location->getRawProviderResponse() : null;
    }
}
