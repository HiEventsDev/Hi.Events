<?php

namespace HiEvents\Services\Domain\Abuse\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuspiciousActivityFindingDTO extends BaseDataObject
{
    /**
     * @param  array<int, SuspiciousActivityItemDTO>  $items
     */
    public function __construct(
        public readonly string $type,
        public readonly string $summary,
        public readonly array $items,
        public readonly string $fingerprint,
        public readonly string $nextStep,
        public readonly ?string $adminPath = null,
        public readonly ?string $adminLinkLabel = null,
    ) {}

    public function withItems(array $items): self
    {
        return new self(
            type: $this->type,
            summary: $this->summary,
            items: $items,
            fingerprint: $this->fingerprint,
            nextStep: $this->nextStep,
            adminPath: $this->adminPath,
            adminLinkLabel: $this->adminLinkLabel,
        );
    }
}
