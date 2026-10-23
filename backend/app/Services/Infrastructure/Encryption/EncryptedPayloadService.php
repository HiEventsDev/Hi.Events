<?php

namespace HiEvents\Services\Infrastructure\Encryption;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\EncryptedPayloadPurpose;
use HiEvents\Services\Infrastructure\Encryption\Exception\DecryptionFailedException;
use HiEvents\Services\Infrastructure\Encryption\Exception\EncryptedPayloadExpiredException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;

class EncryptedPayloadService
{
    private Encrypter $encrypter;

    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    public function encryptPayload(array $payload, EncryptedPayloadPurpose $purpose, ?Carbon $expiry = null): string
    {
        $expiryTime = $expiry ? $expiry->toIso8601String() : Carbon::now()->addHours(48)->toIso8601String();
        $payload['purpose'] = $purpose->value;
        $payload['exp'] = $expiryTime;

        return $this->encrypter->encrypt($payload);
    }

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException
     */
    public function decryptPayload(string $encryptedPayload, EncryptedPayloadPurpose $purpose): array
    {
        try {
            $decrypted = $this->encrypter->decrypt($encryptedPayload);
        } catch (DecryptException) {
            throw new DecryptionFailedException(__('Payload could not be decrypted.'));
        }

        if (! is_array($decrypted) || $this->purposeOf($decrypted) !== $purpose) {
            throw new DecryptionFailedException(__('Payload could not be decrypted.'));
        }

        if (! isset($decrypted['exp']) || (new Carbon($decrypted['exp']))->isPast()) {
            throw new EncryptedPayloadExpiredException(__('Payload has expired or is invalid.'));
        }

        return $decrypted;
    }

    private function purposeOf(array $payload): ?EncryptedPayloadPurpose
    {
        if (isset($payload['purpose'])) {
            return is_string($payload['purpose']) ? EncryptedPayloadPurpose::tryFrom($payload['purpose']) : null;
        }

        return match (true) {
            isset($payload['user_id']) => EncryptedPayloadPurpose::INVITATION,
            isset($payload['email']) => EncryptedPayloadPurpose::EMAIL_CHANGE,
            default => EncryptedPayloadPurpose::EMAIL_CONFIRMATION,
        };
    }
}
