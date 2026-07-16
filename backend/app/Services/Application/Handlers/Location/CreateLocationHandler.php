<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Location;

use HiEvents\DomainObjects\Generated\LocationDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\LocationRepositoryInterface;
use HiEvents\Services\Application\Handlers\Location\DTO\UpsertLocationDTO;
use HiEvents\Services\Domain\Location\LocationDataSanitizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class CreateLocationHandler
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly LocationDataSanitizer $sanitizer,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(UpsertLocationDTO $dto): LocationDomainObject
    {
        try {
            return $this->databaseManager->transaction(fn () => $this->createOrReuse($dto));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findExistingProviderPlace($dto) ?? throw $exception;
        }
    }

    private function createOrReuse(UpsertLocationDTO $dto): LocationDomainObject
    {
        $existing = $this->findExistingProviderPlace($dto);

        if ($existing !== null) {
            return $existing;
        }

        return $this->locationRepository->create([
            LocationDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::LOCATION_PREFIX),
            LocationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            LocationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            LocationDomainObjectAbstract::NAME => $this->sanitizer->sanitizeText($dto->name),
            LocationDomainObjectAbstract::STRUCTURED_ADDRESS => $this->sanitizer->sanitizeAddress($dto->structured_address->toArray()),
            LocationDomainObjectAbstract::LATITUDE => $dto->latitude,
            LocationDomainObjectAbstract::LONGITUDE => $dto->longitude,
            LocationDomainObjectAbstract::PROVIDER => $dto->provider,
            LocationDomainObjectAbstract::PROVIDER_PLACE_ID => $dto->provider_place_id,
            LocationDomainObjectAbstract::RAW_PROVIDER_RESPONSE => $this->sanitizer->cachedRawProviderResponse($dto->provider, $dto->provider_place_id),
        ]);
    }

    private function findExistingProviderPlace(UpsertLocationDTO $dto): ?LocationDomainObject
    {
        if ($dto->provider === null || $dto->provider_place_id === null) {
            return null;
        }

        return $this->locationRepository->findFirstWhere([
            LocationDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
            LocationDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
            LocationDomainObjectAbstract::PROVIDER => $dto->provider,
            LocationDomainObjectAbstract::PROVIDER_PLACE_ID => $dto->provider_place_id,
        ]);
    }
}
