<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\UpdateOrganizerLocationDTO;
use HiEvents\Services\Domain\Location\LocationOwnershipValidator;

class UpdateOrganizerLocationHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly LocationOwnershipValidator $locationOwnershipValidator,
    ) {}

    public function handle(UpdateOrganizerLocationDTO $dto): OrganizerDomainObject
    {
        $existing = $this->organizerRepository->findFirstWhere([
            'id' => $dto->organizer_id,
            'account_id' => $dto->account_id,
        ]);

        if ($existing === null) {
            throw new ResourceNotFoundException(
                __('Organizer :id not found', ['id' => $dto->organizer_id]),
            );
        }

        $this->locationOwnershipValidator->assertOwnedBy(
            $dto->location_id,
            $dto->organizer_id,
            $dto->account_id,
        );

        $this->organizerRepository->updateWhere(
            attributes: ['location_id' => $dto->location_id],
            where: [
                'id' => $dto->organizer_id,
                'account_id' => $dto->account_id,
            ],
        );

        return $this->organizerRepository->findFirstWhere([
            'id' => $dto->organizer_id,
            'account_id' => $dto->account_id,
        ]);
    }
}
