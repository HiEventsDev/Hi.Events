<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use Illuminate\Database\DatabaseManager;

class UpdateContactHandler
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly ContactLookupService $contactLookupService,
        private readonly ContactEnrichmentService $enrichmentService,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(UpsertContactDTO $dto): CrmContactDomainObject
    {
        $contact = $this->contactLookupService->findForOrganizer($dto->organizer_id, $dto->contact_id);

        $nameIsManual = $dto->first_name !== null || $dto->last_name !== null;

        $this->databaseManager->transaction(function () use ($dto, $contact, $nameIsManual): void {
            $this->projectionRepository->lockOrganizer($dto->organizer_id);

            $this->contactRepository->updateWhere(
                attributes: [
                    CrmContactDomainObjectAbstract::FIRST_NAME => $dto->first_name,
                    CrmContactDomainObjectAbstract::LAST_NAME => $dto->last_name,
                    CrmContactDomainObjectAbstract::NAME_IS_MANUAL => $nameIsManual,
                ],
                where: [
                    CrmContactDomainObjectAbstract::ID => $contact->getId(),
                    CrmContactDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                ],
            );

            if (! $nameIsManual) {
                $this->projectionRepository->refreshContacts($dto->organizer_id, [$contact->getId()]);
            }
        });

        $this->cacheVersion->bumpAfterCommit($dto->organizer_id);

        $updated = $this->contactLookupService->findForOrganizer($dto->organizer_id, $contact->getId());
        $this->enrichmentService->attachProfile($updated);

        return $updated;
    }
}
