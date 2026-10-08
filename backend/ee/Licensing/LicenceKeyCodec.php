<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

use Carbon\CarbonImmutable;
use HiEvents\Enterprise\Licensing\DTO\LicencePayloadDTO;
use HiEvents\Enterprise\Licensing\Exceptions\InvalidLicenceKeyException;
use JsonException;
use SodiumException;
use Throwable;

class LicenceKeyCodec
{
    public const PREFIX = 'hiev1';

    /**
     * @param  array<string, string>  $trustedKeys
     */
    public function __construct(
        private readonly array $trustedKeys = TrustedKeys::KEYS,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     * @throws SodiumException
     */
    public function encode(array $payload, string $base64SecretKey): string
    {
        $body = self::PREFIX.'.'.self::toBase64Url(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = sodium_crypto_sign_detached($body, base64_decode($base64SecretKey, true));

        return $body.'.'.self::toBase64Url($signature);
    }

    /**
     * @throws InvalidLicenceKeyException
     */
    public function decode(string $key): LicencePayloadDTO
    {
        $parts = explode('.', trim($key));

        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            throw new InvalidLicenceKeyException(__('The licence key is not in a recognised format'));
        }

        [$prefix, $encodedPayload, $encodedSignature] = $parts;

        $payload = $this->decodePayload($encodedPayload);
        $publicKey = $this->publicKeyFor($payload['kid'] ?? null);

        if (! $this->signatureIsValid("$prefix.$encodedPayload", $encodedSignature, $publicKey)) {
            throw new InvalidLicenceKeyException(__('The licence key signature is invalid'));
        }

        return $this->toPayloadDTO($payload);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidLicenceKeyException
     */
    private function decodePayload(string $encodedPayload): array
    {
        try {
            $payload = json_decode(self::fromBase64Url($encodedPayload), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new InvalidLicenceKeyException(__('The licence key could not be read'));
        }

        if (! is_array($payload)) {
            throw new InvalidLicenceKeyException(__('The licence key could not be read'));
        }

        return $payload;
    }

    /**
     * @throws InvalidLicenceKeyException
     */
    private function publicKeyFor(mixed $kid): string
    {
        if (! is_string($kid) || ! isset($this->trustedKeys[$kid])) {
            throw new InvalidLicenceKeyException(__('The licence key was signed with an unknown key. Upgrading Hi.Events may fix this'));
        }

        return base64_decode($this->trustedKeys[$kid], true) ?: '';
    }

    private function signatureIsValid(string $body, string $encodedSignature, string $publicKey): bool
    {
        try {
            $signature = self::fromBase64Url($encodedSignature);

            return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
                && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                && sodium_crypto_sign_verify_detached($signature, $body, $publicKey);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidLicenceKeyException
     */
    private function toPayloadDTO(array $payload): LicencePayloadDTO
    {
        $plan = Plan::tryFrom((string) ($payload['plan'] ?? ''))
            ?? throw new InvalidLicenceKeyException(__('The licence key is for an unknown plan. Upgrading Hi.Events may fix this'));

        foreach (['lid', 'customer', 'issued_at', 'expires_at'] as $field) {
            if (! is_string($payload[$field] ?? null) || $payload[$field] === '') {
                throw new InvalidLicenceKeyException(__('The licence key is missing :field', ['field' => $field]));
            }
        }

        try {
            $issuedAt = CarbonImmutable::parse($payload['issued_at'], 'UTC');
            $expiresAt = CarbonImmutable::parse($payload['expires_at'], 'UTC');
        } catch (Throwable) {
            throw new InvalidLicenceKeyException(__('The licence key has an invalid date'));
        }

        $addOns = array_filter(array_map(
            static fn (mixed $feature) => is_string($feature) ? LicensedFeature::tryFrom($feature) : null,
            is_array($payload['add'] ?? null) ? $payload['add'] : [],
        ));

        $features = [];
        foreach ([...$plan->features(), ...$addOns] as $feature) {
            $features[$feature->value] = $feature;
        }

        return new LicencePayloadDTO(
            lid: $payload['lid'],
            customer: $payload['customer'],
            plan: $plan,
            features: array_values($features),
            issued_at: $issuedAt,
            expires_at: $expiresAt,
        );
    }

    private static function toBase64Url(string $value): string
    {
        return sodium_bin2base64($value, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * @throws SodiumException
     */
    private static function fromBase64Url(string $value): string
    {
        return sodium_base642bin($value, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
