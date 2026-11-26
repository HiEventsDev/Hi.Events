<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\UnsubscribeStatusDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\EmailMask;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\PhoneMask;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class CampaignUnsubscribeService
{
    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly CampaignUnsubscribeTokenService $tokenService,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly DatabaseManager $databaseManager,
        private readonly ConsentRecorder $consentRecorder,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function status(string $token): UnsubscribeStatusDTO
    {
        return $this->statusOf($this->recipientFor($token));
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function unsubscribe(string $token): UnsubscribeStatusDTO
    {
        $recipient = $this->recipientFor($token);
        $organizerId = (int) $recipient->organizer_id;

        if ($this->isText($recipient)) {
            $this->consentRecorder->recordSmsOptOutForRecipient(
                $organizerId,
                (int) $recipient->id,
                $recipient->phone ?? $recipient->contact_phone,
                CrmConsentSource::UNSUBSCRIBE_LINK,
            );

            return $this->statusOf($this->recipientFor($token));
        }

        $this->databaseManager->transaction(function () use ($recipient, $organizerId): void {
            $this->projectionRepository->lockOrganizer($organizerId);
            $contactId = $this->contactRepository->findContactIdByEmail($organizerId, $recipient->email);

            if ($contactId === null) {
                $this->contactRepository->strengthenDeletedRecipientSuppression($organizerId, (int) $recipient->id, CrmEmailMarketingStatus::UNSUBSCRIBED);

                return;
            }

            $this->contactRepository->recordRecipientConsent(
                organizerId: $organizerId,
                contactId: $contactId,
                email: $recipient->email,
                action: CrmConsentAction::OPT_OUT,
                source: CrmConsentSource::UNSUBSCRIBE_LINK,
                recipientId: (int) $recipient->id,
            );
            $this->projectionRepository->refreshContacts($organizerId, [$contactId]);
            $this->cacheVersion->bumpAfterCommit($organizerId);
        });

        return $this->statusOf($this->recipientFor($token));
    }

    /**
     * @throws ResourceNotFoundException
     * @throws ResourceConflictException
     */
    public function resubscribe(string $token): UnsubscribeStatusDTO
    {
        $organizerId = (int) $this->recipientFor($token)->organizer_id;

        $this->databaseManager->transaction(function () use ($token, $organizerId): void {
            $this->projectionRepository->lockOrganizer($organizerId);
            $recipient = $this->recipientFor($token);

            if (! $this->canResubscribe($recipient)) {
                throw new ResourceConflictException(__('This email address cannot be subscribed again from this link.'));
            }

            $this->contactRepository->recordRecipientConsent(
                organizerId: $organizerId,
                contactId: (int) $recipient->contact_id,
                email: $recipient->email,
                action: CrmConsentAction::OPT_IN,
                source: CrmConsentSource::CONTACT,
                recipientId: (int) $recipient->id,
            );
            $this->projectionRepository->refreshContacts($organizerId, [(int) $recipient->contact_id]);
            $this->cacheVersion->bumpAfterCommit($organizerId);
        });

        return $this->statusOf($this->recipientFor($token));
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function recipientFor(string $token): object
    {
        $recipientId = $this->tokenService->recipientIdFrom($token);
        $recipient = $recipientId === null ? null : $this->campaignRepository->findRecipient($recipientId);

        if ($recipient === null) {
            throw new ResourceNotFoundException(__('This unsubscribe link is not valid.'));
        }

        return $recipient;
    }

    private function statusOf(object $recipient): UnsubscribeStatusDTO
    {
        $tombstone = $recipient->contact_id === null
            ? $this->contactRepository->findDeletedRecipientTombstone((int) $recipient->organizer_id, (int) $recipient->id)
            : null;

        return new UnsubscribeStatusDTO(
            organizer_name: $this->organizerName((int) $recipient->organizer_id),
            email: $recipient->contact_id === null ? null : EmailMask::mask($recipient->email),
            is_unsubscribed: match (true) {
                $recipient->contact_id !== null => $this->isContactUnsubscribed($recipient),
                $tombstone === null => true,
                $this->isText($recipient) => (bool) $tombstone->sms_opted_out,
                default => in_array($tombstone->suppression, [CrmEmailMarketingStatus::UNSUBSCRIBED->value, CrmEmailMarketingStatus::COMPLAINED->value], true),
            },
            can_resubscribe: $this->canResubscribe($recipient),
            channel: ($this->isText($recipient) ? CrmMarketingChannel::SMS : CrmMarketingChannel::EMAIL)->value,
            phone: $this->isText($recipient) && $recipient->phone !== null ? PhoneMask::mask($recipient->phone) : null,
        );
    }

    private function isContactUnsubscribed(object $recipient): bool
    {
        if ($this->isText($recipient)) {
            return ($recipient->phone !== null && $recipient->contact_phone !== $recipient->phone)
                || $recipient->sms_marketing_status !== CrmSmsMarketingStatus::SUBSCRIBED->value;
        }

        return $recipient->email_marketing_status !== CrmEmailMarketingStatus::SUBSCRIBED->value;
    }

    private function canResubscribe(object $recipient): bool
    {
        return ! $this->isText($recipient)
            && $recipient->contact_id !== null
            && $recipient->contact_email === $recipient->email
            && $recipient->email_marketing_status === CrmEmailMarketingStatus::UNSUBSCRIBED->value
            && $this->contactRepository->wasLastOptedOutByLink((int) $recipient->contact_id, (int) $recipient->id);
    }

    private function isText(object $recipient): bool
    {
        return $recipient->phone_country !== null;
    }

    private function organizerName(int $organizerId): string
    {
        return $this->organizerRepository->findFirstWhere(['id' => $organizerId])?->getName()
            ?? (string) config('app.name');
    }
}
