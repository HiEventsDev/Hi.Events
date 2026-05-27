<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateLocationHandler
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly LocationDataSanitizer $sanitizer,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int $locationId, UpsertLocationDTO $dto): LocationDomainObject
    {
        return $this->databaseManager->transaction(function () use ($locationId, $dto) {
            $location = $this->locationRepository->findFirstWhere([
                LocationDomainObjectAbstract::ID => $locationId,
                LocationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                LocationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            ]);

            if ($location === null) {
                throw new ResourceNotFoundException(__('Location not found'));
            }

            return $this->locationRepository->updateFromArray($location->getId(), [
                LocationDomainObjectAbstract::NAME => $this->sanitizer->sanitizeText($dto->name),
                LocationDomainObjectAbstract::STRUCTURED_ADDRESS => $this->sanitizer->sanitizeAddress($dto->structured_address->toArray()),
                LocationDomainObjectAbstract::LATITUDE => $dto->latitude,
                LocationDomainObjectAbstract::LONGITUDE => $dto->longitude,
                LocationDomainObjectAbstract::PROVIDER => $dto->provider,
                LocationDomainObjectAbstract::PROVIDER_PLACE_ID => $dto->provider_place_id,
                LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE => $this->sanitizer->fetchRawProviderResponse($dto->provider, $dto->provider_place_id),
            ]);
        });
    }
}
