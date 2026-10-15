<?php

namespace HiEvents\Http\Attributes;

use Attribute;
use HiEvents\DomainObjects\Enums\Permission;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RequiresPermission
{
    /** @var Permission[] */
    public array $permissions;

    public function __construct(Permission ...$permissions)
    {
        $this->permissions = $permissions;
    }
}
