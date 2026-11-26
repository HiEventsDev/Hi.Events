<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\DomainObjects\Enums\CrmConsentAction;
use HiEvents\DomainObjects\Enums\CrmConsentSource;
use HiEvents\DomainObjects\Generated\CrmContactDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CrmSmsConnectionDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CrmSmsMarketingStatus;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSmsConnectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignReportService;
use HiEvents\Enterprise\Crm\Services\Domain\Consent\ConsentRecorder;

class SmsWebhookService
{
    private const OPT_OUT_KEYWORDS = ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'OPTOUT', 'REVOKE'];

    private const OPT_IN_KEYWORDS = ['START', 'UNSTOP'];

    private const PROVIDER_OPT_OUT = 'STOP';

    private const PROVIDER_OPT_IN = 'START';

    private const DELIVERED_STATUSES = ['delivered'];

    private const FAILED_STATUSES = ['undelivered', 'failed'];

    public function __construct(
        private readonly CrmSmsConnectionRepositoryInterface $connectionRepository,
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly SmsConnectionService $connectionService,
        private readonly SmsProviderInterface $provider,
        private readonly ConsentRecorder $consentRecorder,
        private readonly CampaignReportService $reportService,
    ) {}

    /**
     * @param  array<string, string>  $params
     */
    public function authenticate(string $publicId, bool $isInbound, array $params, string $signature): ?CrmSmsConnectionDomainObject
    {
        $connection = $this->connectionRepository->findFirstWhere([CrmSmsConnectionDomainObjectAbstract::PUBLIC_ID => $publicId]);

        if ($connection === null) {
            return null;
        }

        $url = $isInbound ? $this->connectionService->inboundUrl($publicId) : $this->connectionService->statusUrl($publicId);

        return $this->provider->isValidSignature($connection->getAuthToken(), $url, $params, $signature) ? $connection : null;
    }

    public function recordStatus(CrmSmsConnectionDomainObject $connection, string $messageId, string $status): void
    {
        $status = strtolower($status);

        if (! in_array($status, [...self::DELIVERED_STATUSES, ...self::FAILED_STATUSES], true)) {
            return;
        }

        $campaignId = $this->campaignRepository->recordSmsDeliveryStatus(
            $connection->getOrganizerId(),
            $messageId,
            in_array($status, self::DELIVERED_STATUSES, true),
            now(),
        );

        if ($campaignId !== null) {
            $this->reportService->forgetDeliveryStats([$campaignId]);
        }
    }

    public function recordReply(CrmSmsConnectionDomainObject $connection, string $from, string $body, string $providerDecision): void
    {
        $decision = strtoupper(trim($providerDecision));
        $keyword = strtoupper(trim($body));
        $optsOut = $decision === '' ? in_array($keyword, self::OPT_OUT_KEYWORDS, true) : $decision === self::PROVIDER_OPT_OUT;
        $optsIn = $decision === '' ? in_array($keyword, self::OPT_IN_KEYWORDS, true) : $decision === self::PROVIDER_OPT_IN;

        if (! $optsOut && ! $optsIn) {
            return;
        }

        if ($optsOut) {
            $this->consentRecorder->recordSmsOptOutForNumber($connection->getOrganizerId(), $from, CrmConsentSource::SMS_REPLY);

            return;
        }

        $unsubscribed = $this->contactRepository->findWhere([
            CrmContactDomainObjectAbstract::ORGANIZER_ID => $connection->getOrganizerId(),
            CrmContactDomainObjectAbstract::PHONE => $from,
            CrmContactDomainObjectAbstract::SMS_MARKETING_STATUS => CrmSmsMarketingStatus::UNSUBSCRIBED->value,
        ]);

        /** @var CrmContactDomainObject $contact */
        foreach ($unsubscribed as $contact) {
            $this->consentRecorder->recordSmsConsent(
                organizerId: $connection->getOrganizerId(),
                contactId: $contact->getId(),
                phone: $from,
                action: CrmConsentAction::OPT_IN,
                source: CrmConsentSource::SMS_REPLY,
            );
        }
    }
}
