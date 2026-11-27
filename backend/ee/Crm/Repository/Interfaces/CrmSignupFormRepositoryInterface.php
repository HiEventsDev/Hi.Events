<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmSignupFormDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends RepositoryInterface<CrmSignupFormDomainObject>
 */
interface CrmSignupFormRepositoryInterface extends RepositoryInterface
{
    public function hideOthersFromOrganizerPage(int $organizerId, int $exceptFormId): void;

    public function recordSubmission(int $formId): void;

    public function recordConfirmation(int $formId): void;
}
