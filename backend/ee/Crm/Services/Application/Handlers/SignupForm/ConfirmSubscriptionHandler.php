<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm;

use HiEvents\DomainObjects\CrmPendingSubscriptionDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmContactSource;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmPendingSubscriptionDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmSignupFormDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmPendingSubscriptionStatus;
use HiEvents\Enterprise\Crm\Repository\DTO\DeletedContactConsentDTO;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmPendingSubscriptionRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSignupFormRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\SignupForm\DTO\SubscriptionConfirmationDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactService;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncService;
use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SubscriptionConfirmationReader;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class ConfirmSubscriptionHandler
{
    public function __construct(
        private readonly SubscriptionConfirmationReader $reader,
        private readonly CrmPendingSubscriptionRepositoryInterface $pendingRepository,
        private readonly CrmSignupFormRepositoryInterface $signupFormRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly DeletedContactService $deletedContactService,
        private readonly ContactSyncService $contactSyncService,
        private readonly ConsentRecorder $consentRecorder,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function handle(string $token): SubscriptionConfirmationDTO
    {
        $pending = $this->reader->find($token);
        $organizer = $pending === null ? null : $this->organizerRepository->findFirstWhere(['id' => $pending->getOrganizerId()]);

        if ($pending === null || $organizer === null || ! $this->reader->isConfirmable($pending, $organizer)) {
            return $this->reader->describe($pending);
        }

        $this->databaseManager->transaction(function () use ($pending, $organizer): void {
            $this->projectionRepository->lockOrganizer($organizer->getId());

            $locked = $this->pendingRepository->findPendingForUpdate($pending->getId());

            if ($locked === null || ! $this->reader->isConfirmable($locked, $organizer)) {
                return;
            }

            [$contactId, $consentWhenDeleted] = $this->resolveContact($locked, $organizer->getAccountId());

            if ($consentWhenDeleted !== null) {
                $this->contactSyncService->projectEmailHistory($organizer->getAccountId(), $organizer->getId(), $locked->getEmail());
            }

            if ($consentWhenDeleted?->sms_opted_out) {
                $this->consentRecorder->recordSmsConsent(
                    organizerId: $organizer->getId(),
                    contactId: $contactId,
                    phone: null,
                    action: CrmConsentAction::OPT_OUT,
                    source: CrmConsentSource::MANUAL,
                );
            }

            $this->contactRepository->insertConsentEvent(
                organizerId: $organizer->getId(),
                contactId: $contactId,
                email: $locked->getEmail(),
                action: CrmConsentAction::OPT_IN,
                source: CrmConsentSource::FORM,
                sourceId: $locked->getId(),
                userId: null,
            );

            $form = $locked->getFormId() === null ? null : $this->signupFormRepository->findFirstWhere([
                CrmSignupFormDomainObjectAbstract::ID => $locked->getFormId(),
            ]);

            if ($form !== null) {
                $this->contactRepository->attachTags($organizer->getId(), $contactId, $form->getTagIds() ?? []);
                $this->signupFormRepository->recordConfirmation($form->getId());
            }

            $this->pendingRepository->supersedePending($organizer->getId(), $locked->getEmail());
            $this->pendingRepository->updateWhere(
                attributes: [
                    CrmPendingSubscriptionDomainObjectAbstract::STATUS => CrmPendingSubscriptionStatus::CONFIRMED->value,
                    CrmPendingSubscriptionDomainObjectAbstract::CONFIRMED_AT => now()->toDateTimeString(),
                ],
                where: [CrmPendingSubscriptionDomainObjectAbstract::ID => $locked->getId()],
            );

            $this->projectionRepository->refreshContacts($organizer->getId(), [$contactId]);
        });

        $this->cacheVersion->bumpAfterCommit($organizer->getId());

        return $this->reader->describe($this->pendingRepository->findFirstWhere([CrmPendingSubscriptionDomainObjectAbstract::ID => $pending->getId()]));
    }

    /**
     * @return array{0: int, 1: DeletedContactConsentDTO|null}
     */
    private function resolveContact(CrmPendingSubscriptionDomainObject $pending, int $accountId): array
    {
        $organizerId = $pending->getOrganizerId();
        $email = $pending->getEmail();
        $contactId = $this->contactRepository->findContactIdByEmail($organizerId, $email);

        if ($contactId !== null) {
            $this->useConfirmedAddress($pending, $contactId);

            return [$contactId, null];
        }

        $consentWhenDeleted = $this->deletedContactService->isDeleted($organizerId, $email)
            ? $this->deletedContactService->restore($organizerId, $email)
            : null;

        $contact = $this->contactRepository->createWithEmail([
            CrmContactDomainObjectAbstract::ACCOUNT_ID => $accountId,
            CrmContactDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmContactDomainObjectAbstract::EMAIL => $email,
            CrmContactDomainObjectAbstract::FIRST_NAME => $pending->getFirstName(),
            CrmContactDomainObjectAbstract::LAST_NAME => $pending->getLastName(),
            CrmContactDomainObjectAbstract::LOCALE => $pending->getLocale(),
            CrmContactDomainObjectAbstract::SOURCE => CrmContactSource::FORM->value,
            CrmContactDomainObjectAbstract::FIRST_SEEN_AT => now(),
        ]);

        return [$contact->getId(), $consentWhenDeleted];
    }

    private function useConfirmedAddress(CrmPendingSubscriptionDomainObject $pending, int $contactId): void
    {
        $organizerId = $pending->getOrganizerId();
        $contact = $this->contactRepository->findFirstWhere([
            CrmContactDomainObjectAbstract::ID => $contactId,
            CrmContactDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($contact === null) {
            return;
        }

        if ($contact->getEmail() !== $pending->getEmail()) {
            $emailId = $this->contactRepository->findContactEmailId($organizerId, $contactId, $pending->getEmail());

            if ($emailId !== null) {
                $this->contactRepository->setPrimaryEmail($organizerId, $contactId, $emailId);
            }
        }

        $hasNoName = trim(($contact->getFirstName() ?? '').($contact->getLastName() ?? '')) === '';

        if ($hasNoName && ! $contact->getNameIsManual() && ($pending->getFirstName() !== null || $pending->getLastName() !== null)) {
            $this->contactRepository->updateWhere(
                attributes: [
                    CrmContactDomainObjectAbstract::FIRST_NAME => $pending->getFirstName(),
                    CrmContactDomainObjectAbstract::LAST_NAME => $pending->getLastName(),
                ],
                where: [CrmContactDomainObjectAbstract::ID => $contactId],
            );
        }
    }
}
