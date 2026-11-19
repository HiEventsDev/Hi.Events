<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\Helper\Url;
use HiEvents\Services\Infrastructure\Url\PublicApiUrl;
use Illuminate\Config\Repository;

class CampaignUnsubscribeTokenService
{
    private const SHORT_SIGNATURE_LENGTH = 12;

    private const SAMPLE_SHORT_TOKEN = 'preview.000000000000';

    public function __construct(
        private readonly Repository $config,
        private readonly PublicApiUrl $publicApiUrl,
    ) {}

    public function tokenFor(int $recipientId): string
    {
        return $recipientId.'.'.$this->signature($recipientId);
    }

    public function shortTokenFor(int $recipientId): string
    {
        return $recipientId.'.'.$this->shortSignature($recipientId);
    }

    public function recipientIdFrom(string $token): ?int
    {
        [$recipientId, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! ctype_digit($recipientId)) {
            return null;
        }

        $expected = strlen($signature) === self::SHORT_SIGNATURE_LENGTH
            ? $this->shortSignature((int) $recipientId)
            : $this->signature((int) $recipientId);

        return hash_equals($expected, $signature) ? (int) $recipientId : null;
    }

    public function smsUrl(int $recipientId): string
    {
        return sprintf(Url::getFrontEndUrlFromConfig(Url::CRM_SMS_UNSUBSCRIBE), $this->shortTokenFor($recipientId));
    }

    public function sampleSmsUrl(): string
    {
        return sprintf(Url::getFrontEndUrlFromConfig(Url::CRM_SMS_UNSUBSCRIBE), self::SAMPLE_SHORT_TOKEN);
    }

    public function pageUrl(int $recipientId): string
    {
        return sprintf(Url::getFrontEndUrlFromConfig(Url::CRM_UNSUBSCRIBE), $this->tokenFor($recipientId));
    }

    public function oneClickUrl(int $recipientId): string
    {
        return $this->publicApiUrl->to('public/crm/unsubscribe/'.$this->tokenFor($recipientId));
    }

    private function shortSignature(int $recipientId): string
    {
        return substr(hash_hmac('sha256', 'crm-campaign-sms-unsubscribe|'.$recipientId, (string) $this->config->get('app.key')), 0, self::SHORT_SIGNATURE_LENGTH);
    }

    private function signature(int $recipientId): string
    {
        return substr(hash_hmac('sha256', 'crm-campaign-unsubscribe|'.$recipientId, (string) $this->config->get('app.key')), 0, 40);
    }
}
