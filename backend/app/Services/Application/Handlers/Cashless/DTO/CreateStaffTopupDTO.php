<?php

namespace HiEvents\Services\Application\Handlers\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;

class CreateStaffTopupDTO extends BaseDataObject
{
    public function __construct(
        public string $sales_point_short_id,
        public string $attendee_public_id,
        public float $amount,
        public CashlessStaffPaymentMethod $payment_method,
        public string $client_reference_id,
        public ?string $session_token = null,
        public ?string $notes = null,
    ) {}
}
