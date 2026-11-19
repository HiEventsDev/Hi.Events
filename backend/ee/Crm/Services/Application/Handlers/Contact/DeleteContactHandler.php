<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use HiEvents\Exceptions\ResourceNotFoundException;

class DeleteContactHandler
{
    public function __construct(
        private readonly ContactLookupService $contactLookupService,
        private readonly DeletedContactService $deletedContactService,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId): void
    {
        $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);

        $this->deletedContactService->delete($organizerId, [$contact->getId()]);
        $this->cacheVersion->bumpAfterCommit($organizerId);
    }
}
