<?php

declare(strict_types=1);

namespace TicketKitten\Http\Request\Order;

use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\CompleteOrderValidator;

class CompleteOrderRequest extends BaseRequest
{
    public function rules(CompleteOrderValidator $orderValidator): array
    {
        return $orderValidator->rules();
    }

    public function messages(): array
    {
        return app(CompleteOrderValidator::class)->messages();
    }
}
