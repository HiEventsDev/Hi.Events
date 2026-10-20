<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\AccountSsoDomainStatus;

class AccountSsoDomainDomainObject extends Generated\AccountSsoDomainDomainObjectAbstract
{
    public const SUSPEND_AFTER_FAILING_DAYS = 7;

    public const TXT_RECORD_PREFIX = '_hievents-verify.';

    public const TXT_VALUE_PREFIX = 'hievents-verify=';

    public function getDomainStatus(): AccountSsoDomainStatus
    {
        if ($this->getVerifiedAt() === null) {
            return AccountSsoDomainStatus::PENDING;
        }

        if ($this->getFailingSince() === null) {
            return AccountSsoDomainStatus::VERIFIED;
        }

        return Carbon::parse($this->getFailingSince())->addDays(self::SUSPEND_AFTER_FAILING_DAYS)->isPast()
            ? AccountSsoDomainStatus::SUSPENDED
            : AccountSsoDomainStatus::FAILING;
    }

    public function isUsable(): bool
    {
        return in_array($this->getDomainStatus(), [AccountSsoDomainStatus::VERIFIED, AccountSsoDomainStatus::FAILING], true);
    }

    public function getTxtRecordName(): string
    {
        return self::TXT_RECORD_PREFIX.$this->getDomain();
    }

    public function getTxtRecordValue(): string
    {
        return self::TXT_VALUE_PREFIX.$this->getVerificationToken();
    }
}
