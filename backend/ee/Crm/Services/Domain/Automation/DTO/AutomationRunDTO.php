<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\EventDomainObject;

class AutomationRunDTO extends BaseDataObject
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  int[]|null  $contact_ids
     */
    public function __construct(
        public readonly string $run_key,
        public readonly ?string $label,
        public readonly ?EventDomainObject $event,
        public readonly array $attributes,
        public readonly bool $keep_when_nobody_to_send_to = false,
        public readonly ?array $contact_ids = null,
    ) {}
}
