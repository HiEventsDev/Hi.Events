<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Account;

use HiEvents\DomainObjects\Enums\AttributionSourceType;
use Illuminate\Config\Repository;

class AttributionSourceClassifier
{
    private const PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'paid-social', 'display', 'retargeting'];

    private const PAID_CLICK_IDS = ['gclid', 'gbraid', 'wbraid'];

    private const SEARCH_ENGINE_HOST_PATTERN = '/(^|\.)(google|bing|duckduckgo|yahoo|ecosia|brave|baidu|yandex)(\.(com|co|org|net))?\.[a-z]{2,4}$/';

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function classify(
        ?string $utmMedium,
        ?string $referrerUrl,
        ?string $gclid,
        ?string $fbclid,
        ?array $utmRaw,
    ): AttributionSourceType {
        if ($this->hasPaidClickId($gclid, $utmRaw)) {
            return AttributionSourceType::PAID;
        }

        $normalizedMedium = $utmMedium === null ? null : strtolower(trim($utmMedium));

        if ($normalizedMedium !== null && in_array($normalizedMedium, self::PAID_MEDIUMS, true)) {
            return AttributionSourceType::PAID;
        }

        if ($fbclid !== null) {
            return AttributionSourceType::REFERRAL;
        }

        $referrerHost = $this->normalizeHost($referrerUrl === null ? null : parse_url($referrerUrl, PHP_URL_HOST));

        if ($referrerHost === null || $this->isInternalHost($referrerHost) || $this->isSearchEngineHost($referrerHost)) {
            return AttributionSourceType::ORGANIC;
        }

        return AttributionSourceType::REFERRAL;
    }

    public function hasPaidClickId(?string $gclid, ?array $utmRaw): bool
    {
        if ($gclid !== null) {
            return true;
        }

        foreach (self::PAID_CLICK_IDS as $clickId) {
            if (! empty($utmRaw[$clickId])) {
                return true;
            }
        }

        return false;
    }

    private function isInternalHost(string $referrerHost): bool
    {
        foreach ([$this->config->get('app.url'), $this->config->get('app.frontend_url')] as $internalUrl) {
            $internalHost = $this->normalizeHost(is_string($internalUrl) ? parse_url($internalUrl, PHP_URL_HOST) : null);

            if ($internalHost !== null && $this->isSameSite($referrerHost, $internalHost)) {
                return true;
            }
        }

        return false;
    }

    private function isSearchEngineHost(string $referrerHost): bool
    {
        return (bool) preg_match(self::SEARCH_ENGINE_HOST_PATTERN, $referrerHost);
    }

    private function isSameSite(string $hostA, string $hostB): bool
    {
        return $hostA === $hostB
            || str_ends_with($hostA, '.'.$hostB)
            || str_ends_with($hostB, '.'.$hostA);
    }

    private function normalizeHost(mixed $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
