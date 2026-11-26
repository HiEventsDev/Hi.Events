<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sms;

use HiEvents\DomainObjects\CrmSmsConnectionDomainObject;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsConnectionService;

class GetSmsConnectionHandler
{
    public function __construct(
        private readonly SmsConnectionService $connectionService,
    ) {}

    public function handle(int $organizerId): ?CrmSmsConnectionDomainObject
    {
        return $this->connectionService->findForOrganizer($organizerId);
    }
}
