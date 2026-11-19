<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class SetPrimaryContactEmailHandler
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly ContactLookupService $contactLookupService,
        private readonly ContactEnrichmentService $enrichmentService,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId, int $contactEmailId): CrmContactDomainObject
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);

        $this->databaseManager->transaction(function () use ($organizerId, $contact, $contactEmailId): void {
            if (! $this->contactRepository->setPrimaryEmail($organizerId, $contact->getId(), $contactEmailId)) {
                throw new ResourceNotFoundException(__('Email address not found'));
            }

            $this->projectionRepository->refreshContacts($organizerId, [$contact->getId()]);
            $this->cacheVersion->bumpAfterCommit($organizerId);
        });

        $updated = $this->contactLookupService->findForOrganizer($organizerId, $contact->getId());
        $this->enrichmentService->attachProfile($updated);

        return $updated;
    }
}
