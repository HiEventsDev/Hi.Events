<?php

namespace HiEvents\Http\Request\Order;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Validators\Rules\RulesHelper;

class EditOrderRequest extends BaseRequest
{
    private ?bool $isBoxOfficeOrder = null;

    public function rules(): array
    {
        return [
            'email' => $this->isBoxOfficeOrder() ? RulesHelper::OPTIONAL_EMAIL : RulesHelper::REQUIRED_EMAIL,
            'first_name' => RulesHelper::REQUIRED_STRING,
            'last_name' => $this->isBoxOfficeOrder() ? RulesHelper::STRING : RulesHelper::REQUIRED_STRING,
            'notes' => RulesHelper::OPTIONAL_TEXT_MEDIUM_LENGTH,
        ];
    }

    private function isBoxOfficeOrder(): bool
    {
        if ($this->isBoxOfficeOrder !== null) {
            return $this->isBoxOfficeOrder;
        }

        $order = app(OrderRepositoryInterface::class)->findFirstWhere([
            'id' => $this->route('order_id'),
            'event_id' => $this->route('event_id'),
        ]);

        return $this->isBoxOfficeOrder = $order?->isBoxOfficeOrder() ?? false;
    }
}
