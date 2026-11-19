<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Note;

use HiEvents\DomainObjects\CrmContactNoteDomainObject;
use HiEvents\DomainObjects\Generated\CrmContactNoteDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactNoteRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class CreateContactNoteHandler
{
    public function __construct(
        private readonly CrmContactNoteRepositoryInterface $noteRepository,
        private readonly ContactLookupService $contactLookupService,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId, int $userId, string $authorName, string $note): CrmContactNoteDomainObject
    {
        return $this->databaseManager->transaction(function () use ($organizerId, $contactId, $userId, $authorName, $note): CrmContactNoteDomainObject {
            $this->projectionRepository->lockOrganizer($organizerId);

            $contact = $this->contactLookupService->findForOrganizer($organizerId, $contactId);

            return $this->noteRepository->create([
                CrmContactNoteDomainObjectAbstract::CONTACT_ID => $contact->getId(),
                CrmContactNoteDomainObjectAbstract::ORGANIZER_ID => $organizerId,
                CrmContactNoteDomainObjectAbstract::USER_ID => $userId,
                CrmContactNoteDomainObjectAbstract::NOTE => trim($note),
            ])->setAuthorName($authorName);
        });
    }
}
