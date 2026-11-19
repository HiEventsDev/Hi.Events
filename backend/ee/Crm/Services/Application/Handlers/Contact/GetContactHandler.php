<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetContactHandler
{
    public function __construct(
        private readonly ContactLookupService $contactLookupService,
        private readonly ContactEnrichmentService $enrichmentService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId): CrmContactDomainObject
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);
        $this->enrichmentService->attachProfile($contact);

        return $contact;
    }
}
