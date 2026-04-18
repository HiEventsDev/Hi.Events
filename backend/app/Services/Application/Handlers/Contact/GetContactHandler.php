<?php

namespace HiEvents\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ContactRepositoryInterface;

readonly class GetContactHandler
{
    public function __construct(
        private ContactRepositoryInterface $contactRepository,
        private AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    public function handle(int $contactId, int $accountId): ContactDomainObject
    {
        $contact = $this->contactRepository->findFirstWhere([
            ContactDomainObject::ID => $contactId,
            ContactDomainObject::ACCOUNT_ID => $accountId,
        ]);

        $attendees = $this->attendeeRepository->findWhere([
            AttendeeDomainObject::CONTACT_ID => $contactId,
        ]);

        $contact->setAttendees($attendees);

        return $contact;
    }
}
