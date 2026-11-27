<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\Enterprise\Crm\Exceptions\CampaignOrganizerDeletedException;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenCatalog;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignRecipientDTO;

class CampaignSmsPreviewService
{
    public function __construct(
        private readonly CampaignSmsComposer $composer,
        private readonly SmsConnectionService $connectionService,
        private readonly SmsLengthCalculator $lengthCalculator,
    ) {}

    /**
     * @throws CampaignOrganizerDeletedException
     */
    public function preview(CrmCampaignDomainObject $campaign, ?CampaignRecipientDTO $recipient): SmsPreviewDTO
    {
        $variants = [];

        foreach ($this->countriesByOptOutMethod($campaign->getOrganizerId()) as $countries) {
            $text = $this->composer->compose($campaign, null, $recipient, $countries[0]);
            $length = $this->lengthCalculator->measure($text);

            $variants[] = new SmsPreviewVariantDTO(
                countries: array_map(static fn (SmsCountry $country) => $country->value, $countries),
                text: $text,
                characters: $length->characters,
                parts: $length->parts,
                is_unicode: $length->is_unicode,
            );
        }

        return new SmsPreviewDTO(
            variants: $variants,
            uses_placeholders: CampaignTokenCatalog::used((string) $campaign->getBody()) !== [],
        );
    }

    /**
     * @return SmsCountry[][]
     */
    private function countriesByOptOutMethod(int $organizerId): array
    {
        $enabled = $this->connectionService->enabledCountries($this->connectionService->findForOrganizer($organizerId));
        $countries = array_values(array_filter(
            SmsCountry::cases(),
            static fn (SmsCountry $country) => $enabled === [] || in_array($country->value, $enabled, true),
        ));

        return array_values(array_filter([
            array_values(array_filter($countries, static fn (SmsCountry $country) => ! $country->optsOutByReply())),
            array_values(array_filter($countries, static fn (SmsCountry $country) => $country->optsOutByReply())),
        ]));
    }
}
