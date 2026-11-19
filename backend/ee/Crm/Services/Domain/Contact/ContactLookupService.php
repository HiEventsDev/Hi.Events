<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class ContactLookupService
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function findForOrganizer(int $organizerId, int $contactId): CrmContactDomainObject
    {
        $contact = $this->contactRepository->findFirstWhere([
            CrmContactDomainObjectAbstract::ID => $contactId,
            CrmContactDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($contact === null) {
            throw new ResourceNotFoundException(__('Contact not found'));
        }

        return $contact;
    }
}
