<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class RazorpayRefundPayload extends BaseDataObject
{
    public function __construct(
        public readonly RazorpayRefundDTO $refund,
    ) {}
}