<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Suppression\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\Enterprise\Crm\Repository\DTO\SuppressionDetailDTO;

class SuppressionLookupResultDTO extends BaseDataObject
{
    public const INVALID = 'invalid';

    public const NOT_FOUND = 'not_found';

    public const CONTACT = 'contact';

    public const DELETED = 'deleted';

    public function __construct(
        public readonly string $email,
        public readonly string $result,
        public readonly ?CrmContactDomainObject $contact = null,
        public readonly ?SuppressionDetailDTO $suppression = null,
        public readonly ?string $deleted_suppression = null,
    ) {}
}
