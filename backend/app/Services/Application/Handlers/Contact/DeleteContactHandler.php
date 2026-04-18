<?php

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;

readonly class DeleteContactHandler
{
    public function __construct(
        private ContactRepositoryInterface $contactRepository,
        private AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    public function handle(int $contactId, int $accountId): void
    {
        $this->contactRepository->findFirstWhere([
            ContactDomainObject::ID => $contactId,
            ContactDomainObject::ACCOUNT_ID => $accountId,
        ]);

        $this->attendeeRepository->updateWhere(
            [AttendeeDomainObject::CONTACT_ID => $contactId],
            [AttendeeDomainObject::CONTACT_ID => null],
        );

        $this->contactRepository->deleteById($contactId);
    }
}
