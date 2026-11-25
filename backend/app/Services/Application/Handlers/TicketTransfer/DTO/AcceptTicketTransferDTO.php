<?php

namespace HiEvents\Services\Application\Handlers\TicketTransfer\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AcceptTicketTransferDTO extends BaseDataObject
{
    /**
     * @param  array<int, array{question_id: int|string, response: mixed}>  $questions
     */
    public function __construct(
        public readonly string $token,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly array $questions,
        public readonly ?string $ip_address,
        public readonly ?string $user_agent,
    ) {}
}
