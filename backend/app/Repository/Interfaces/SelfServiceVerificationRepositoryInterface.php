<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\SelfServiceVerificationDomainObject;

/**
 * @extends RepositoryInterface<SelfServiceVerificationDomainObject>
 */
interface SelfServiceVerificationRepositoryInterface extends RepositoryInterface
{
    public function deleteExpiredBefore(string $expiredBefore): int;
}
