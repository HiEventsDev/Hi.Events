<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\PosSessionDomainObjectAbstract;
use Illuminate\Support\Collection;

class PosSessionDomainObject extends PosSessionDomainObjectAbstract
{
    private ?Collection $transactions = null;

    public function getTransactions(): ?Collection
    {
        return $this->transactions;
    }

    public function setTransactions(?Collection $transactions): self
    {
        $this->transactions = $transactions;
        return $this;
    }
}
