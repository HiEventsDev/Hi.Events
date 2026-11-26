<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Consent;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use Illuminate\Database\DatabaseManager;

class ConsentRecorder
{
    public function __construct(
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function record(
        CrmContactDomainObject $contact,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId = null,
        ?int $userId = null,
    ): void {
        $this->recordForEmail(
            organizerId: $contact->getOrganizerId(),
            contactId: $contact->getId(),
            email: $contact->getEmail(),
            action: $action,
            source: $source,
            sourceId: $sourceId,
            userId: $userId,
        );
    }

    public function recordForEmail(
        int $organizerId,
        int $contactId,
        string $email,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId = null,
        ?int $userId = null,
    ): void {
        $this->databaseManager->transaction(function () use ($organizerId, $contactId, $email, $action, $source, $sourceId, $userId): void {
            $this->projectionRepository->lockOrganizer($organizerId);

            if ($this->contactRepository->findFirstWhere(['id' => $contactId, 'organizer_id' => $organizerId]) === null) {
                return;
            }

            $this->contactRepository->insertConsentEvent(
                organizerId: $organizerId,
                contactId: $contactId,
                email: $email,
                action: $action,
                source: $source,
                sourceId: $sourceId,
                userId: $userId,
            );

            $this->projectionRepository->refreshContacts($organizerId, [$contactId]);
        });

        $this->cacheVersion->bumpAfterCommit($organizerId);
    }

    public function recordSmsOptOut(CrmContactDomainObject $contact, int $userId): void
    {
        $organizerId = $contact->getOrganizerId();

        $this->databaseManager->transaction(function () use ($organizerId, $contact, $userId): void {
            $this->projectionRepository->lockOrganizer($organizerId);

            $number = $this->contactRepository->findFirstWhere(['id' => $contact->getId(), 'organizer_id' => $organizerId])?->getPhone();

            if ($number !== null) {
                $this->optOutEveryContactWithNumber($organizerId, $number, CrmConsentSource::MANUAL, null, $userId);
            }
        });

        $this->cacheVersion->bumpAfterCommit($organizerId);
    }

    public function recordSmsOptOutForNumber(int $organizerId, string $phone, CrmConsentSource $source): void
    {
        $this->databaseManager->transaction(function () use ($organizerId, $phone, $source): void {
            $this->projectionRepository->lockOrganizer($organizerId);
            $this->optOutEveryContactWithNumber($organizerId, $phone, $source, null, null);
        });

        $this->cacheVersion->bumpAfterCommit($organizerId);
    }

    public function recordSmsOptOutForRecipient(int $organizerId, int $recipientId, ?string $phone, CrmConsentSource $source): void
    {
        $this->databaseManager->transaction(function () use ($organizerId, $recipientId, $phone, $source): void {
            $this->projectionRepository->lockOrganizer($organizerId);

            if ($phone !== null) {
                $this->optOutEveryContactWithNumber($organizerId, $phone, $source, $recipientId, null);
            }

            $this->contactRepository->markDeletedRecipientOptedOutOfTexts($organizerId, $recipientId);
        });

        $this->cacheVersion->bumpAfterCommit($organizerId);
    }

    private function optOutEveryContactWithNumber(int $organizerId, string $phone, CrmConsentSource $source, ?int $sourceId, ?int $userId): void
    {
        $contacts = $this->contactRepository->findWhere([
            CrmContactDomainObjectAbstract::ORGANIZER_ID => $organizerId,
            CrmContactDomainObjectAbstract::PHONE => $phone,
        ]);

        /** @var CrmContactDomainObject $contact */
        foreach ($contacts as $contact) {
            $this->contactRepository->insertSmsConsentEvent(
                organizerId: $organizerId,
                contactId: $contact->getId(),
                email: $contact->getEmail(),
                phone: $phone,
                action: CrmConsentAction::OPT_OUT,
                source: $source,
                sourceId: $sourceId,
                userId: $userId,
            );
        }

        $this->projectionRepository->refreshContacts($organizerId, $contacts->map(static fn (CrmContactDomainObject $contact) => $contact->getId())->all());
    }

    public function recordSmsConsent(
        int $organizerId,
        int $contactId,
        ?string $phone,
        CrmConsentAction $action,
        CrmConsentSource $source,
        ?int $sourceId = null,
        ?int $userId = null,
    ): void {
        $this->databaseManager->transaction(function () use ($organizerId, $contactId, $phone, $action, $source, $sourceId, $userId): void {
            $this->projectionRepository->lockOrganizer($organizerId);

            $current = $this->contactRepository->findFirstWhere(['id' => $contactId, 'organizer_id' => $organizerId]);
            $number = $phone ?? $current?->getPhone();

            if ($current === null || $number === null) {
                return;
            }

            $this->contactRepository->insertSmsConsentEvent(
                organizerId: $organizerId,
                contactId: $current->getId(),
                email: $current->getEmail(),
                phone: $number,
                action: $action,
                source: $source,
                sourceId: $sourceId,
                userId: $userId,
            );

            $this->projectionRepository->refreshContacts($organizerId, [$current->getId()]);
        });

        $this->cacheVersion->bumpAfterCommit($organizerId);
    }

    /**
     * @param  int[]  $contactIds
     * @return int[] the contacts that were opted out
     */
    public function recordOptOuts(int $organizerId, array $contactIds, ?int $userId): array
    {
        $optedOut = $this->databaseManager->transaction(function () use ($organizerId, $contactIds, $userId): array {
            $this->projectionRepository->lockOrganizer($organizerId);

            $optedOut = $this->contactRepository->recordOptOuts($organizerId, $contactIds, $userId);
            $this->projectionRepository->refreshContacts($organizerId, $optedOut);

            return $optedOut;
        });

        $this->cacheVersion->bumpAfterCommit($organizerId);

        return $optedOut;
    }
}
