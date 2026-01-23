<?php

namespace HiEvents\Services\Domain\Order\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Values\MoneyValue;

class ApplicationFeeValuesDTO extends BaseDataObject
{
    public function __construct(
        public MoneyValue  $grossApplicationFee,
        public MoneyValue  $netApplicationFee,
        public ?float      $applicationFeeVatRate = null,
        public ?MoneyValue $applicationFeeVatAmount = null,
    )
    {
    }
}
