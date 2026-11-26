<?php

namespace HiEvents\DomainObjects;

class CrmAutomationDomainObject extends Generated\CrmAutomationDomainObjectAbstract
{
    private int $sentCount = 0;

    private ?string $lastSentAt = null;

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): static
    {
        $this->sentCount = $sentCount;

        return $this;
    }

    public function getLastSentAt(): ?string
    {
        return $this->lastSentAt;
    }

    public function setLastSentAt(?string $lastSentAt): static
    {
        $this->lastSentAt = $lastSentAt;

        return $this;
    }
}
