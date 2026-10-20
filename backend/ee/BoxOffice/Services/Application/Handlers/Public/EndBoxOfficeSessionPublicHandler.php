<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public;

use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;

class EndBoxOfficeSessionPublicHandler
{
    public function __construct(
        private readonly BoxOfficeSessionService $sessionService,
    ) {}

    public function handle(?string $token): void
    {
        $this->sessionService->forget($token);
    }
}
