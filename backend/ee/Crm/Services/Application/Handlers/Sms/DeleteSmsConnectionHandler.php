<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms;

use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;

class DeleteSmsConnectionHandler
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
    ) {}

    public function handle(int $organizerId): void
    {
        $this->connectionService->disconnect($organizerId);
    }
}
