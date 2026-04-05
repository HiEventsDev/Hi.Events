<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\MembershipPlanDomainObjectAbstract;
use Illuminate\Support\Collection;

class MembershipPlanDomainObject extends MembershipPlanDomainObjectAbstract
{
    private ?Collection $memberships = null;

    public function getMemberships(): ?Collection
    {
        return $this->memberships;
    }

    public function setMemberships(?Collection $memberships): self
    {
        $this->memberships = $memberships;
        return $this;
    }
}
