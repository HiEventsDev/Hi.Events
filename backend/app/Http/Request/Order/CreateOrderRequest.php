<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Order;

use Illuminate\Validation\ValidationException;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\CreateOrderValidator;

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
