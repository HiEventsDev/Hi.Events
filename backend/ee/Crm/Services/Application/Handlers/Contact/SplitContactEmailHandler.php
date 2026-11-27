<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class SplitContactEmailHandler
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
     * @throws ResourceConflictException
     */
    public function handle(int $organizerId, int $contactId, int $contactEmailId): CrmContactDomainObject
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);

        if ($this->contactRepository->findEmails($contact->getId())->doesntContain('id', $contactEmailId)) {
            throw new ResourceNotFoundException(__('Email address not found'));
        }

        $newContactId = $this->databaseManager->transaction(function () use ($organizerId, $contact, $contactEmailId): int {
            $newContactId = $this->contactRepository->splitEmail($organizerId, $contact->getId(), $contactEmailId)
                ?? throw new ResourceConflictException(__('This email address cannot be separated from the contact.'));

            $this->projectionRepository->refreshContacts($organizerId, [$contact->getId(), $newContactId]);
            $this->cacheVersion->bumpAfterCommit($organizerId);

            return $newContactId;
        });

        try {
            $updated = $this->contactLookupService->findForOrganizer($organizerId, $contact->getId());
        } catch (ResourceNotFoundException) {
            $updated = $this->contactLookupService->findForOrganizer($organizerId, $newContactId);
        }
        $this->enrichmentService->attachProfile($updated);

        return $updated;
    }
}
