<?php

namespace HiEvents\Services\Domain\Message\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\MessagingEligibilityFailureEnum;

class MessagingEligibilityFailureDTO extends BaseDataObject
{
    /**
     * @param int $accountId
     * @param int $eventId
     * @param MessagingEligibilityFailureEnum[] $failures
     */
    public function __construct(
        public readonly int $accountId,
        public readonly int $eventId,
        public readonly array $failures,
    ) {
    }

    /**
     * @return string[]
     */
    public function getFailureValues(): array
    {
        return array_map(fn(MessagingEligibilityFailureEnum $failure) => $failure->value, $this->failures);
    }
}
