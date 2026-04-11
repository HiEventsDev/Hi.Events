<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Aws;

use HiEvents\Exceptions\SnsSignatureVerificationException;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Http;

class SnsSignatureVerificationService
{
    private const SIGNING_CERT_URL_PATTERN = '/^https:\/\/sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?\/SimpleNotificationService-[a-z0-9]+\.pem$/';

    private const NOTIFICATION_STRING_TO_SIGN_KEYS = [
        'Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type',
    ];

    private const SUBSCRIPTION_STRING_TO_SIGN_KEYS = [
        'Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type',
    ];

    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    /**
     * @throws SnsSignatureVerificationException
     */
    public function verify(array $payload): void
    {
        if (!config('services.ses.sns_verify_signature', true)) {
            return;
        }

        $signingCertUrl = $payload['SigningCertURL'] ?? null;
        $signature = $payload['Signature'] ?? null;

        if (!$signingCertUrl || !$signature) {
            throw new SnsSignatureVerificationException(
                __('SNS message missing SigningCertURL or Signature')
            );
        }

        if (!$this->isValidSigningCertUrl($signingCertUrl)) {
            throw new SnsSignatureVerificationException(
                __('Invalid SNS SigningCertURL: :url', ['url' => $signingCertUrl])
            );
        }

        $certificate = $this->fetchCertificate($signingCertUrl);
        $stringToSign = $this->buildStringToSign($payload);

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            throw new SnsSignatureVerificationException(
                __('Invalid base64 signature in SNS message')
            );
        }

        $publicKey = openssl_pkey_get_public($certificate);
        if ($publicKey === false) {
            throw new SnsSignatureVerificationException(
                __('Unable to extract public key from SNS signing certificate')
            );
        }

        $result = openssl_verify($stringToSign, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA1);

        if ($result !== 1) {
            throw new SnsSignatureVerificationException(
                __('SNS message signature verification failed')
            );
        }
    }

    private function isValidSigningCertUrl(string $url): bool
    {
        return (bool) preg_match(self::SIGNING_CERT_URL_PATTERN, $url);
    }

    private function fetchCertificate(string $url): string
    {
        $response = Http::timeout(5)->get($url);

        if (!$response->successful()) {
            throw new SnsSignatureVerificationException(
                __('Failed to fetch SNS signing certificate from :url', ['url' => $url])
            );
        }

        return $response->body();
    }

    private function buildStringToSign(array $payload): string
    {
        $type = $payload['Type'] ?? '';
        $keys = $type === 'Notification'
            ? self::NOTIFICATION_STRING_TO_SIGN_KEYS
            : self::SUBSCRIPTION_STRING_TO_SIGN_KEYS;

        $stringToSign = '';
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $stringToSign .= $key . "\n" . $payload[$key] . "\n";
            }
        }

        return $stringToSign;
    }
}
