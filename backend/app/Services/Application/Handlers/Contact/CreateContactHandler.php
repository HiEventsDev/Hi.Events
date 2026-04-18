<?php

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use HiEvents\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;

readonly class CreateContactHandler
{
    public function __construct(
        private ContactRepositoryInterface $contactRepository,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function handle(UpsertContactDTO $dto): ContactDomainObject
    {
        $existing = $this->contactRepository->findByEmailAndAccountId($dto->email, $dto->account_id);

        if ($existing !== null) {
            throw new ResourceConflictException(
                __('A contact with this email already exists.')
            );
        }

        return $this->contactRepository->create([
            ContactDomainObject::ACCOUNT_ID => $dto->account_id,
            ContactDomainObject::EMAIL => strtolower($dto->email),
            ContactDomainObject::FIRST_NAME => $dto->wasProvided('first_name') ? $dto->first_name : null,
            ContactDomainObject::LAST_NAME => $dto->wasProvided('last_name') ? $dto->last_name : null,
            ContactDomainObject::ATTRIBUTES => $dto->wasProvided('attributes') ? $dto->attributes : [],
            ContactDomainObject::ATTRIBUTES_HISTORY => [],
        ]);
    }
}
