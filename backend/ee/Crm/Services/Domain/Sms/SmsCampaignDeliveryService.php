<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmCampaignRecipientStatus;
use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignDispatchService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignOwnerNotifier;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Traits\Localizable;

class SmsCampaignDeliveryService
{
    use Localizable;

    public const REASON_CONNECTION_REFUSED = 'SMS_CONNECTION_REFUSED';

    public const REASON_DISCONNECTED = 'SMS_DISCONNECTED';

    public function __construct(
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CampaignDispatchService $dispatchService,
        private readonly CampaignSmsComposer $composer,
        private readonly SmsConnectionService $connectionService,
        private readonly ConsentRecorder $consentRecorder,
        private readonly CampaignOwnerNotifier $ownerNotifier,
        private readonly DatabaseManager $databaseManager,
        private readonly Repository $config,
    ) {}

    /**
     * @throws SmsProviderException
     */
    public function deliver(int $recipientId): void
    {
        $recipient = $this->campaignRepository->findRecipient($recipientId);

        if ($recipient === null || $recipient->status !== CrmCampaignRecipientStatus::PENDING->value) {
            return;
        }

        $campaign = $this->campaignRepository->findFirstWhere(['id' => (int) $recipient->campaign_id]);

        if ($campaign?->getStatus() === CrmCampaignStatus::CANCELLED->value) {
            $this->campaignRepository->markRecipientSkipped($recipientId, CrmRecipientFailureReason::CAMPAIGN_STOPPED);

            return;
        }

        if ($campaign?->getStatus() !== CrmCampaignStatus::SENDING->value) {
            return;
        }

        $country = SmsCountry::tryFrom((string) $recipient->phone_country);
        $connection = $this->connectionService->findForOrganizer((int) $recipient->organizer_id);

        if ($connection === null) {
            $this->pauseForLostConnection($campaign, $recipientId, self::REASON_DISCONNECTED);

            return;
        }

        if ($country !== null && ! $country->allowsSendingAt(now())) {
            return;
        }

        $reasonNotTextable = $this->reasonNotTextable($recipient, $country, $connection);

        $boughtSinceLaunch = $reasonNotTextable === null
            && $campaign->getSkipsBuyers()
            && $campaign->getEventId() !== null
            && $this->campaignRepository->hasBoughtSince((int) $recipient->organizer_id, (int) $recipient->contact_id, $campaign->getEventId(), (string) $campaign->getCreatedAt());

        if ($reasonNotTextable !== null || $boughtSinceLaunch) {
            $this->campaignRepository->markRecipientSkipped($recipientId, $reasonNotTextable ?? CrmRecipientFailureReason::ALREADY_BOUGHT);
            $this->dispatchService->markSentIfFinished($campaign->getId());

            return;
        }

        try {
            $body = $this->withLocale($recipient->locale ?? $this->config->get('app.locale'), fn (): string => $this->composer->compose(
                $campaign,
                $recipientId,
                new CampaignRecipientDTO(first_name: $recipient->first_name, last_name: $recipient->last_name, contact_id: (int) $recipient->contact_id),
                $country,
            ));
        } catch (CampaignOrganizerDeletedException) {
            $this->dispatchService->cancelForDeletedOrganizer($campaign->getId());

            return;
        }

        if (! $this->campaignRepository->claimRecipient($recipientId)) {
            return;
        }

        try {
            $providerMessageId = $this->connectionService->send($connection, (string) $recipient->phone, $body, trackDelivery: true);
        } catch (SmsProviderException $exception) {
            $this->handleProviderError($campaign, $recipient, $exception);

            return;
        }

        $this->campaignRepository->markRecipientSent($recipientId, $providerMessageId, now());
        $this->campaignRepository->recordCampaignActivity($recipientId, now());
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

    private function reasonNotTextable(object $recipient, ?SmsCountry $country, CrmSmsConnectionDomainObject $connection): ?CrmRecipientFailureReason
    {
        return match (true) {
            $recipient->contact_id === null, $recipient->contact_email === null => CrmRecipientFailureReason::CONTACT_DELETED,
            $recipient->contact_phone !== $recipient->phone => CrmRecipientFailureReason::NO_PHONE,
            $recipient->sms_marketing_status === CrmSmsMarketingStatus::UNSUBSCRIBED->value => CrmRecipientFailureReason::UNSUBSCRIBED,
            $recipient->sms_marketing_status !== CrmSmsMarketingStatus::SUBSCRIBED->value => CrmRecipientFailureReason::NO_CONSENT,
            $country === null || ! in_array($country->value, $this->connectionService->enabledCountries($connection), true) => CrmRecipientFailureReason::COUNTRY_NOT_ENABLED,
            default => null,
        };
    }

    /**
     * @throws SmsProviderException
     */
    private function handleProviderError(CrmCampaignDomainObject $campaign, object $recipient, SmsProviderException $exception): void
    {
        if (! $exception->isPermanent && ! $exception->mayHaveBeenAccepted) {
            $this->campaignRepository->unclaimRecipient((int) $recipient->id);

            throw $exception;
        }

        if ($exception->connectionRefused) {
            $this->pauseForLostConnection($campaign, (int) $recipient->id, self::REASON_CONNECTION_REFUSED);

            return;
        }

        if ($exception->recipientOptedOut) {
            $this->campaignRepository->markRecipientFailed((int) $recipient->id, CrmRecipientFailureReason::PROVIDER_SUPPRESSED);
            $this->consentRecorder->recordSmsOptOutForRecipient((int) $recipient->organizer_id, (int) $recipient->id, (string) $recipient->phone, CrmConsentSource::CAMPAIGN);
        } else {
            $this->campaignRepository->markRecipientFailed(
                (int) $recipient->id,
                $exception->isPermanent ? CrmRecipientFailureReason::PROVIDER_REJECTED : CrmRecipientFailureReason::TIMEOUT,
            );
        }

        $this->dispatchService->markSentIfFinished($campaign->getId());
    }

    private function pauseForLostConnection(CrmCampaignDomainObject $campaign, int $recipientId, string $reason): void
    {
        $paused = $this->databaseManager->transaction(function () use ($campaign, $recipientId, $reason): bool {
            $this->campaignRepository->unclaimRecipient($recipientId);

            $paused = $this->campaignRepository->transitionStatus($campaign->getId(), [CrmCampaignStatus::SENDING->value], [
                CrmCampaignDomainObjectAbstract::STATUS => CrmCampaignStatus::PAUSED->value,
                CrmCampaignDomainObjectAbstract::STATUS_REASON => $reason,
            ]);

            $this->campaignRepository->skipPendingRecipients($campaign->getId());

            return $paused;
        });

        if ($paused) {
            $this->ownerNotifier->notifyStoppedSending($campaign, static fn (): string => $reason === self::REASON_DISCONNECTED
                ? __('Twilio was disconnected from this organizer while the campaign was sending.')
                : __('Twilio no longer accepts the account details or Messaging Service saved for this organizer. Check your Twilio connection before sending again.'));
        }
    }
}
