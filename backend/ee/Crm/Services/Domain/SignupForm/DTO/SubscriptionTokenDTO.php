<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\SignupForm\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SubscriptionTokenDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $token,
        public readonly string $hash,
    ) {}
}
