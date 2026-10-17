<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\AccountSsoConnectionType;
use HiEvents\DomainObjects\Status\AccountSsoConnectionStatus;

class AccountSsoConnectionDomainObject extends Generated\AccountSsoConnectionDomainObjectAbstract
{
    public const PROVIDER_KEY_PREFIX = 'connection-';

    public function getConnectionType(): AccountSsoConnectionType
    {
        return AccountSsoConnectionType::from($this->getType());
    }

    public function isActive(): bool
    {
        return $this->getStatus() === AccountSsoConnectionStatus::ACTIVE->value;
    }

    public function isEnforcing(): bool
    {
        return $this->isActive() && $this->getEnforceSso();
    }

    public function getProviderKey(): string
    {
        return self::PROVIDER_KEY_PREFIX.$this->getId();
    }
}
