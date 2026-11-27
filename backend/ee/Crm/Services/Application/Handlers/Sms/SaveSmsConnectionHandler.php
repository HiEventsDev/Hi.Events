<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms;

use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;

class SaveSmsConnectionHandler
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
    ) {}

    /**
     * @param  string[]  $enabledCountries
     *
     * @throws SmsProviderException
     */
    public function handle(int $accountId, int $organizerId, string $accountSid, ?string $authToken, string $messagingServiceSid, array $enabledCountries): CrmSmsConnectionDomainObject
    {
        return $this->connectionService->save($accountId, $organizerId, $accountSid, $authToken, $messagingServiceSid, $enabledCountries);
    }
}
