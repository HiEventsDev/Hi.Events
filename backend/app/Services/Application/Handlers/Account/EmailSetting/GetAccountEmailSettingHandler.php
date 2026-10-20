<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Account\EmailSetting;

use HiEvents\DomainObjects\AccountEmailSettingDomainObject;
use HiEvents\Repository\Interfaces\AccountEmailSettingRepositoryInterface;

class GetAccountEmailSettingHandler
{
    public function __construct(
        private readonly AccountEmailSettingRepositoryInterface $emailSettingRepository,
    ) {
    }

    public function handle(int $accountId): ?AccountEmailSettingDomainObject
    {
        return $this->emailSettingRepository->findByAccountId($accountId);
    }
}
