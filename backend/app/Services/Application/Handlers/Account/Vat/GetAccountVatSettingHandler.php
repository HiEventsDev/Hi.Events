<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account\Vat;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;

class GetAccountVatSettingHandler
{
    public function __construct(
        private readonly AccountVatSettingRepositoryInterface $vatSettingRepository,
    ) {
    }

    public function handle(int $accountId): ?AccountVatSettingDomainObject
    {
        return $this->vatSettingRepository->findByAccountId($accountId);
    }
}
