<?php

namespace HiEvents\Services\Domain\Message\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\MessagingTierViolationEnum;

class MessagingTierViolationDTO extends BaseDataObject
{
    /**
     * @param  MessagingTierViolationEnum[]  $violations
     */
    public function __construct(
        public readonly int $accountId,
        public readonly string $tierName,
        public readonly array $violations,
    ) {}

    public function getFirstViolationMessage(): string
    {
        return $this->violations[0]->getMessage();
    }
}
