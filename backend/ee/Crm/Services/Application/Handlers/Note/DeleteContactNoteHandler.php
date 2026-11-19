<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Note;

use HiEvents\DomainObjects\Generated\CrmContactNoteDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactNoteRepositoryInterface;
use HiEvents\Exceptions\ResourceNotFoundException;

class DeleteContactNoteHandler
{
    public function __construct(
        private readonly CrmContactNoteRepositoryInterface $noteRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $contactId, int $noteId): void
    {
        $deleted = $this->noteRepository->deleteWhere([
            CrmContactNoteDomainObjectAbstract::ID => $noteId,
            CrmContactNoteDomainObjectAbstract::CONTACT_ID => $contactId,
            CrmContactNoteDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($deleted === 0) {
            throw new ResourceNotFoundException(__('Note not found'));
        }
    }
}
