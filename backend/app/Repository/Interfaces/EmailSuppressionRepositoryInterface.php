<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EmailSuppressionDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<EmailSuppressionDomainObject>
 */
interface EmailSuppressionRepositoryInterface extends RepositoryInterface
{
    public function isEmailSuppressed(string $email, ?int $accountId, string $reason): bool;

    public function findByEmail(string $email, ?int $accountId = null): Collection;

    public function findOrCreateSuppression(array $uniqueAttributes, array $additionalValues): EmailSuppressionDomainObject;
}
