<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\Enterprise\Crm\Exceptions\InvalidSegmentDefinitionException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignAudienceService;
use HiEvents\Exceptions\ResourceNotFoundException;

class SmsCampaignEstimator
{
    public function __construct(
        private readonly CampaignAudienceService $audienceService,
        private readonly SmsConnectionService $connectionService,
        private readonly CrmContactRepositoryInterface $contactRepository,
    ) {}

    /**
     * @param  SmsPreviewVariantDTO[]  $variants
     */
    public function estimate(CrmCampaignDomainObject $campaign, array $variants): SmsCampaignEstimateDTO
    {
        $recipientsByCountry = $this->recipientsByCountry($campaign);
        $countries = [];

        foreach ($variants as $variant) {
            foreach (array_intersect($variant->countries, array_keys($recipientsByCountry)) as $country) {
                $countries[] = new SmsCountryEstimateDTO(
                    country: $country,
                    recipients: $recipientsByCountry[$country],
                    parts: $variant->parts,
                    is_sending_now: SmsCountry::from($country)->allowsSendingAt(now()),
                    next_sending_at: SmsCountry::from($country)->nextSendingTime(now())->toIso8601String(),
                );
            }
        }

        return new SmsCampaignEstimateDTO(
            countries: $countries,
            recipients: array_sum($recipientsByCountry),
            message_parts: array_sum(array_map(static fn (SmsCountryEstimateDTO $country) => $country->recipients * $country->parts, $countries)),
        );
    }

    /**
     * @return array<string, int>
     */
    private function recipientsByCountry(CrmCampaignDomainObject $campaign): array
    {
        $countries = $this->connectionService->enabledCountries($this->connectionService->findForOrganizer($campaign->getOrganizerId()));

        try {
            $segment = $this->audienceService->compile($campaign);
        } catch (ResourceNotFoundException|InvalidSegmentDefinitionException) {
            return [];
        }

        return $this->contactRepository->countTextableByCountry($campaign->getOrganizerId(), $segment, $countries);
    }
}
