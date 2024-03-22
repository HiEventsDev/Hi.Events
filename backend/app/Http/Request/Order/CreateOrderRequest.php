<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Services\Domain\Order\OrderCreateRequestValidationService;

class CreateOrderRequest extends BaseRequest
{
    /**
     * @see OrderCreateRequestValidationService
     */
    public function rules(): array
    {
        return [];
    }
}
