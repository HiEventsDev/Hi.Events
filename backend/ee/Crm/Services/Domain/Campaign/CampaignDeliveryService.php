<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Status\CrmCampaignRecipientStatus;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\DomainObjects\Status\CrmEmailMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Mail\CampaignMail;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use HiEvents\Services\Domain\Email\Delivery\EmailProviderMessageIdResolver;
use Illuminate\Config\Repository;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Traits\Localizable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CampaignDeliveryService
{
    use Localizable;

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignMailFactory $mailFactory,
        private readonly CampaignDispatchService $dispatchService,
        private readonly CampaignHealthGuard $healthGuard,
        private readonly EmailProviderMessageIdResolver $providerMessageIdResolver,
        private readonly CampaignTransportErrorClassifier $transportErrorClassifier,
        private readonly ConsentRecorder $consentRecorder,
        private readonly MailManager $mailManager,
        private readonly Repository $config,
    ) {}

    public function deliver(int $recipientId): void
    {
        $recipient = $this->campaignRepository->findRecipient($recipientId);

        if ($recipient === null || $recipient->status !== CrmCampaignRecipientStatus::PENDING->value) {
            return;
        }

        $campaign = $this->campaignRepository->findFirstWhere(['id' => (int) $recipient->campaign_id]);

        if (in_array($campaign?->getStatus(), [CrmCampaignStatus::CANCELLED->value, CrmCampaignStatus::PAUSED->value], true)) {
            $this->campaignRepository->markRecipientSkipped($recipientId, CrmRecipientFailureReason::CAMPAIGN_STOPPED);

            return;
        }

        if ($campaign?->getStatus() !== CrmCampaignStatus::SENDING->value) {
            return;
        }

        if ($this->dispatchService->pauseIfAccountSuspended($campaign)) {
            return;
        }

        $isStillSubscribed = $recipient->contact_id !== null
            && $recipient->contact_email === $recipient->email
            && $recipient->email_marketing_status === CrmEmailMarketingStatus::SUBSCRIBED->value;

        if (! $isStillSubscribed) {
            $this->campaignRepository->markRecipientSkipped($recipientId, $this->reasonNotMailable($recipient));
            $this->dispatchService->markSentIfFinished($campaign->getId());

            return;
        }

        $boughtSinceLaunch = $campaign->getSkipsBuyers()
            && $campaign->getEventId() !== null
            && $this->campaignRepository->hasBoughtSince((int) $recipient->organizer_id, (int) $recipient->contact_id, $campaign->getEventId(), (string) $campaign->getCreatedAt());

        if ($boughtSinceLaunch) {
            $this->campaignRepository->markRecipientSkipped($recipientId, CrmRecipientFailureReason::ALREADY_BOUGHT);
            $this->dispatchService->markSentIfFinished($campaign->getId());

            return;
        }

        $locale = $recipient->locale ?? $this->config->get('app.locale');

        try {
            $mail = $this->withLocale($locale, fn (): CampaignMail => $this->mailFactory->make(
                $campaign,
                $recipientId,
                new CampaignRecipientDTO(first_name: $recipient->first_name, last_name: $recipient->last_name, contact_id: $recipient->contact_id === null ? null : (int) $recipient->contact_id),
            ));
        } catch (CampaignOrganizerDeletedException) {
            $this->dispatchService->cancelForDeletedOrganizer($campaign->getId());

            return;
        }

        if (! $this->campaignRepository->claimRecipient($recipientId)) {
            return;
        }

        try {
            $sentMessage = $this->mailManager
                ->mailer($this->config->get('mail.campaigns.mailer'))
                ->to($recipient->email)
                ->locale($locale)
                ->sendNow($mail);
        } catch (TransportExceptionInterface $exception) {
            $failureReason = $this->transportErrorClassifier->permanentFailureReason($exception);

            if ($failureReason === null) {
                $this->campaignRepository->unclaimRecipient($recipientId);

                throw $exception;
            }

            $this->recordProviderFailure($campaign, $recipient, $failureReason);

            return;
        }

        $this->campaignRepository->markRecipientSent(
            $recipientId,
            $sentMessage === null ? null : $this->providerMessageIdResolver->resolve($sentMessage->getSymfonySentMessage()),
            now(),
        );
        $this->campaignRepository->recordCampaignActivity($recipientId, now());

        if ($this->healthGuard->stopIfUnhealthy($campaign->getId())) {
            return;
        }

        $this->dispatchService->markSentIfFinished($campaign->getId());
    }

    private function reasonNotMailable(object $recipient): CrmRecipientFailureReason
    {
        if ($recipient->contact_id === null) {
            return CrmRecipientFailureReason::CONTACT_DELETED;
        }

        return match ($recipient->email_marketing_status) {
            CrmEmailMarketingStatus::UNSUBSCRIBED->value => CrmRecipientFailureReason::UNSUBSCRIBED,
            CrmEmailMarketingStatus::BOUNCED->value, CrmEmailMarketingStatus::COMPLAINED->value => CrmRecipientFailureReason::BOUNCED,
            CrmEmailMarketingStatus::SUBSCRIBED->value => CrmRecipientFailureReason::EMAIL_CHANGED,
            default => CrmRecipientFailureReason::NO_CONSENT,
        };
    }

    private function recordProviderFailure(CrmCampaignDomainObject $campaign, object $recipient, CrmRecipientFailureReason $reason): void
    {
        $this->campaignRepository->markRecipientFailed((int) $recipient->id, $reason);

        if ($reason === CrmRecipientFailureReason::PROVIDER_SUPPRESSED) {
            $this->consentRecorder->recordForEmail(
                organizerId: (int) $recipient->organizer_id,
                contactId: (int) $recipient->contact_id,
                email: $recipient->email,
                action: CrmConsentAction::BOUNCED,
                source: CrmConsentSource::CAMPAIGN,
                sourceId: (int) $recipient->id,
            );
        }

        if ($this->healthGuard->stopIfUnhealthy($campaign->getId())) {
            return;
        }

        $this->dispatchService->markSentIfFinished($campaign->getId());
    }

    public function markFailed(int $recipientId, CrmRecipientFailureReason $reason): void
    {
        $recipient = $this->campaignRepository->findRecipient($recipientId);

        if ($recipient === null || ! $this->campaignRepository->markRecipientFailed($recipientId, $reason)) {
            return;
        }

        $this->dispatchService->markSentIfFinished((int) $recipient->campaign_id);
    }

    public function markAbandoned(int $recipientId): void
    {
        $wasGoingOut = $this->campaignRepository->findRecipient($recipientId)?->status === CrmCampaignRecipientStatus::SENDING->value;

        $this->markFailed($recipientId, $wasGoingOut ? CrmRecipientFailureReason::TIMEOUT : CrmRecipientFailureReason::SEND_ERROR);
    }
}
