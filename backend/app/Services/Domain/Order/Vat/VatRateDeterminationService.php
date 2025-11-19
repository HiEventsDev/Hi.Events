<?php

namespace HiEvents\Services\Domain\Order\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\Enums\CountryCode;
use Illuminate\Config\Repository;

class VatRateDeterminationService
{
    private float $defaultVatRate;

    private string $defaultVatCountry;

    public function __construct(
        private readonly Repository $config,
    )
    {
        $this->defaultVatRate = $this->config->get('app.tax.default_vat_rate', 0.23);
        $this->defaultVatCountry = $this->config->get('app.tax.default_vat_country', CountryCode::IE->value);
    }

    public function determineVatRatePercentage(AccountVatSettingDomainObject $vatSetting): float
    {
        $country = $vatSetting->getVatCountryCode();
        $hasVatNumber = !empty($vatSetting->getVatNumber());
        $validated = $vatSetting->getVatValidated();
        $isEu = CountryCode::isEuCountry(CountryCode::from($country));

        // 1. Default VAT country (e.g. IE) → Always charge VAT, regardless of VAT number
        if ($country === $this->defaultVatCountry) {
            return $this->defaultVatRate;
        }

        // 2. If outside EU → No VAT
        if (!$isEu) {
            return 0.0;
        }

        // 3. EU B2B with valid VAT → Reverse charge (0%)
        if ($validated && $hasVatNumber) {
            return 0.0;
        }

        // 4. EU but no valid VAT → Charge VAT
        return $this->defaultVatRate;
    }
}
