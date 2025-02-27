<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class WebhookDomainObject extends Generated\WebhookDomainObjectAbstract
{
    public ?Collection $logs = null;

    public function setLogs(Collection $logs): static
    {
        $this->logs = $logs;
        return $this;
    }

    public function getLogs(): ?Collection
    {
        return $this->logs;
    }
}
