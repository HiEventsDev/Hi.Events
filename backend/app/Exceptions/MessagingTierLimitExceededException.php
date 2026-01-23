<?php

namespace HiEvents\Exceptions;

use Exception;
use HiEvents\Services\Domain\Message\DTO\MessagingTierViolationDTO;

class MessagingTierLimitExceededException extends Exception
{
    public function __construct(
        private readonly MessagingTierViolationDTO $violation,
        int $code = 429,
        ?Exception $previous = null
    ) {
        parent::__construct($this->violation->getFirstViolationMessage(), $code, $previous);
    }

    public function getViolation(): MessagingTierViolationDTO
    {
        return $this->violation;
    }
}
