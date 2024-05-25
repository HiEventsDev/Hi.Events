<?php

namespace HiEvents\Services\Infrastructure\Encryption;

use Carbon\Carbon;
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

    public function encryptPayload(array $payload, Carbon $expiry = null): string
    {
        $expiryTime = $expiry ? $expiry->toIso8601String() : Carbon::now()->addHours(48)->toIso8601String();
        $payload['exp'] = $expiryTime;

        return $this->encrypter->encrypt($payload);
    }

    /**
     * @throws DecryptionFailedException
     * @throws EncryptedPayloadExpiredException
     */
    public function decryptPayload(string $encryptedPayload): array
    {
        try {
            $decrypted = $this->encrypter->decrypt($encryptedPayload);

            if (!isset($decrypted['exp']) || (new Carbon($decrypted['exp']))->isPast()) {
                throw new EncryptedPayloadExpiredException(__('Payload has expired or is invalid.'));
            }

        } catch (DecryptException) {
            throw new DecryptionFailedException(__('Payload could not be decrypted.'));
        }

        return $decrypted;
    }
}
