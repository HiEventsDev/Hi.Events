<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Note;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactNoteRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Support\Collection;

class GetContactNotesHandler
{
    public function __construct(
        private readonly CrmContactNoteRepositoryInterface $noteRepository,
        private readonly ContactLookupService $contactLookupService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId): Collection
    {
        return $this->noteRepository->findForContact(
            $this->contactLookupService->findForOrganizer($organizerId, $contactId)->getId(),
        );
    }
}
