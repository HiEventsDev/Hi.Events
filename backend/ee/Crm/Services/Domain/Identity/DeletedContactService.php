<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Identity;

use HiEvents\Enterprise\Crm\Repository\DTO\DeletedContactConsentDTO;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;

class DeletedContactService
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly DeletedContactHasher $hasher,
    ) {}

    public function isDeleted(int $organizerId, string $normalizedEmail): bool
    {
        return $this->contactRepository->hasDeletedEmail($organizerId, $this->hasher->hash($organizerId, $normalizedEmail));
    }

    public function restore(int $organizerId, string $normalizedEmail): DeletedContactConsentDTO
    {
        return $this->contactRepository->forgetDeletedEmail($organizerId, $this->hasher->hash($organizerId, $normalizedEmail));
    }

    /**
     * @param  int[]  $contactIds
     * @return int[] the contacts that were deleted
     */
    public function delete(int $organizerId, array $contactIds): array
    {
        return $this->contactRepository->deleteContacts($organizerId, $contactIds, $this->hasher->salt($organizerId));
    }
}
