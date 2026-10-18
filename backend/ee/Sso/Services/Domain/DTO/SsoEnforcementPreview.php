<?php

namespace HiEvents\Enterprise\Sso\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class SsoEnforcementPreview extends BaseDataObject
{
    /**
     * @param  Collection<int, SsoEnforcementMember>  $members
     */
    public function __construct(
        public readonly Collection $members,
        public readonly int $affectedCount,
        public readonly int $outsideDomainCount,
    ) {}
}
