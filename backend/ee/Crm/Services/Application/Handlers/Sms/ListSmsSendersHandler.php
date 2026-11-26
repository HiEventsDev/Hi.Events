<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms;

use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsSenderDTO;

class ListSmsSendersHandler
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
    ) {}

    /**
     * @return SmsSenderDTO[]
     *
     * @throws SmsProviderException
     */
    public function handle(int $organizerId, string $accountSid, ?string $authToken): array
    {
        return $this->connectionService->listSenders($organizerId, $accountSid, $authToken);
    }
}
