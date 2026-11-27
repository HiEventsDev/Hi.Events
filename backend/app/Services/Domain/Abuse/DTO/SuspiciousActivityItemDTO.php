<?php

namespace HiEvents\Services\Domain\Abuse\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SuspiciousActivityItemDTO extends BaseDataObject
{
    /**
     * @param  array<int, string>  $facts
     */
    public function __construct(
        public readonly string $label,
        public readonly array $facts = [],
        public readonly ?int $accountId = null,
        public readonly ?string $accountSearch = null,
        public readonly ?SuspiciousActivityAccountDTO $account = null,
    ) {}

    public function withAccount(?SuspiciousActivityAccountDTO $account): self
    {
        return new self(
            label: $this->label,
            facts: $this->facts,
            accountId: $this->accountId,
            accountSearch: $this->accountSearch,
            account: $account,
        );
    }
}
