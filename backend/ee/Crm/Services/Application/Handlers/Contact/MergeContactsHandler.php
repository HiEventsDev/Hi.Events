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

class MergeContactsHandler
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
    public function handle(int $organizerId, int $targetContactId, int $sourceContactId): CrmContactDomainObject
    {
        if ($targetContactId === $sourceContactId) {
            throw new ResourceConflictException(__('A contact cannot be merged into itself.'));
        }

        $this->databaseManager->transaction(function () use ($organizerId, $targetContactId, $sourceContactId): void {
            if (! $this->contactRepository->mergeContacts($organizerId, $targetContactId, $sourceContactId)) {
                throw new ResourceNotFoundException(__('Contact not found'));
            }

            $this->projectionRepository->refreshContacts($organizerId, [$targetContactId]);
            $this->cacheVersion->bumpAfterCommit($organizerId);
        });

        $merged = $this->contactLookupService->findForOrganizer($organizerId, $targetContactId);
        $this->enrichmentService->attachProfile($merged);

        return $merged;
    }
}
