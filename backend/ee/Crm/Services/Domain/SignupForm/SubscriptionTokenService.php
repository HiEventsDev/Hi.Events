<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\SignupForm;

use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\DTO\SubscriptionTokenDTO;

class SubscriptionTokenService
{
    private const TOKEN_BYTES = 32;

    public function generate(): SubscriptionTokenDTO
    {
        $token = rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');

        return new SubscriptionTokenDTO(token: $token, hash: $this->hash($token));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
