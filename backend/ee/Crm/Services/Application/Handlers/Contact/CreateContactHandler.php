<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmContactSource;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\DeletedContactRestoreRequiredException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\DTO\UpsertContactDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Contact\ContactEnrichmentService;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use HiEvents\Exceptions\ResourceConflictException;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateContactHandler
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly ContactEmailNormalizer $emailNormalizer,
        private readonly DeletedContactService $deletedContactService,
        private readonly ContactEnrichmentService $enrichmentService,
        private readonly ConsentRecorder $consentRecorder,
        private readonly DatabaseManager $databaseManager,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly ContactSyncService $contactSyncService,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws DeletedContactRestoreRequiredException
     * @throws Throwable
     */
    public function handle(UpsertContactDTO $dto): CrmContactDomainObject
    {
        $email = $this->emailNormalizer->normalize($dto->email);

        if ($email === null) {
            throw new ResourceConflictException(__('Enter a valid email address.'));
        }

        $contact = $this->databaseManager->transaction(function () use ($dto, $email): CrmContactDomainObject {
            $this->projectionRepository->lockOrganizer($dto->organizer_id);

            if ($this->contactRepository->findContactIdByEmail($dto->organizer_id, $email) !== null) {
                throw new ResourceConflictException(__('A contact with this email address already exists.'));
            }

            $wasDeleted = $this->deletedContactService->isDeleted($dto->organizer_id, $email);

            if ($wasDeleted && ! $dto->restore_deleted) {
                throw new DeletedContactRestoreRequiredException(__('A contact with this email address was deleted. Adding it again will bring back their past orders and activity.'));
            }

            $consentWhenDeleted = $this->deletedContactService->restore($dto->organizer_id, $email);

            $contact = $this->contactRepository->createWithEmail([
                CrmContactDomainObjectAbstract::ACCOUNT_ID => $dto->account_id,
                CrmContactDomainObjectAbstract::ORGANIZER_ID => $dto->organizer_id,
                CrmContactDomainObjectAbstract::EMAIL => $email,
                CrmContactDomainObjectAbstract::FIRST_NAME => $dto->first_name,
                CrmContactDomainObjectAbstract::LAST_NAME => $dto->last_name,
                CrmContactDomainObjectAbstract::NAME_IS_MANUAL => $dto->first_name !== null || $dto->last_name !== null,
                CrmContactDomainObjectAbstract::SOURCE => CrmContactSource::MANUAL->value,
            ]);

            if ($consentWhenDeleted->suppression !== null) {
                $this->consentRecorder->record(
                    contact: $contact,
                    action: match ($consentWhenDeleted->suppression) {
                        CrmEmailMarketingStatus::COMPLAINED => CrmConsentAction::COMPLAINED,
                        CrmEmailMarketingStatus::BOUNCED => CrmConsentAction::BOUNCED,
                        default => CrmConsentAction::OPT_OUT,
                    },
                    source: CrmConsentSource::MANUAL,
                );
            }

            if ($wasDeleted) {
                $this->contactSyncService->projectEmailHistory($dto->account_id, $dto->organizer_id, $email);
            }

            if ($consentWhenDeleted->sms_opted_out) {
                $this->consentRecorder->recordSmsConsent(
                    organizerId: $dto->organizer_id,
                    contactId: $contact->getId(),
                    phone: null,
                    action: CrmConsentAction::OPT_OUT,
                    source: CrmConsentSource::MANUAL,
                );
            }

            return $this->contactRepository->findById($contact->getId());
        });

        $this->cacheVersion->bumpAfterCommit($dto->organizer_id);
        $this->enrichmentService->attachProfile($contact);

        return $contact;
    }
}
