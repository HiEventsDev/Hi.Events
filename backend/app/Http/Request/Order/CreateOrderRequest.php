<?php

declare(strict_types=1);

namespace TicketKitten\Http\Request\Order;

use Illuminate\Validation\ValidationException;
use TicketKitten\Http\Request\BaseRequest;
use TicketKitten\Validator\CreateOrderValidator;

class CreateOrderRequest extends BaseRequest
{
    /**
     * @throws ValidationException
     */
    public function rules(CreateOrderValidator $validator): array
    {
        return $validator->rules((int)$this->route()?->parameter('event_id'), $this->request->all());
    }
}
