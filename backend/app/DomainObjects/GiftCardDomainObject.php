<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\GiftCardDomainObjectAbstract;
use Illuminate\Support\Collection;

class GiftCardDomainObject extends GiftCardDomainObjectAbstract
{
    private ?Collection $usages = null;

    public function getUsages(): ?Collection
    {
        return $this->usages;
    }

    public function setUsages(?Collection $usages): self
    {
        $this->usages = $usages;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && strtotime($this->expires_at) < time();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired() && $this->balance > 0;
    }
}
