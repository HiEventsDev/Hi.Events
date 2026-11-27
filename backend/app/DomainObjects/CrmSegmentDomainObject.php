<?php

namespace HiEvents\DomainObjects;

use HiEvents\Enterprise\Crm\Services\Domain\Segment\DTO\SegmentUsageDTO;

class CrmSegmentDomainObject extends Generated\CrmSegmentDomainObjectAbstract
{
    /**
     * @var SegmentUsageDTO[]
     */
    private array $usages = [];

    /**
     * @return SegmentUsageDTO[]
     */
    public function getUsages(): array
    {
        return $this->usages;
    }

    /**
     * @param  SegmentUsageDTO[]  $usages
     */
    public function setUsages(array $usages): static
    {
        $this->usages = $usages;

        return $this;
    }
}
