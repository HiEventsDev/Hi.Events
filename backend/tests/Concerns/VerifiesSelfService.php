<?php

namespace Tests\Concerns;

use HiEvents\Services\Domain\SelfService\SelfServiceVerificationService;

trait VerifiesSelfService
{
    protected function selfServiceHeadersFor(string $email): array
    {
        return [
            SelfServiceVerificationService::SESSION_HEADER => app(SelfServiceVerificationService::class)->startSession($email)->token,
        ];
    }
}
