<?php

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;
use HiEvents\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Services\Domain\Contact\ContactUpsertService;

readonly class UpdateContactHandler
{
    public function __construct(
        private ContactRepositoryInterface $contactRepository,
        private ContactUpsertService $contactUpsertService,
    ) {}

    public function handle(int $contactId, int $accountId, int $userId, UpsertContactDTO $dto): ContactDomainObject
    {
        $contact = $this->contactRepository->findFirstWhere([
            ContactDomainObject::ID => $contactId,
            ContactDomainObject::ACCOUNT_ID => $accountId,
        ]);

        $updates = [];
        if ($dto->wasProvided('first_name')) {
            $updates[ContactDomainObject::FIRST_NAME] = $dto->first_name;
        }
        if ($dto->wasProvided('last_name')) {
            $updates[ContactDomainObject::LAST_NAME] = $dto->last_name;
        }

        if (! empty($updates)) {
            $this->contactRepository->updateFromArray($contactId, $updates);
        }

        if ($dto->wasProvided('attributes') && ! empty($dto->attributes)) {
            if (! empty($updates)) {
                $contact = $this->contactRepository->findById($contactId);
            }

            return $this->contactUpsertService->updateContactAttributes($contact, $dto->attributes, $userId);
        }

        if (! empty($updates)) {
            return $this->contactRepository->findById($contactId);
        }

        return $contact;
    }
}
