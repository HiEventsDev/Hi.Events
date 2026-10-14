<?php

namespace HiEvents\Http\Request\Order;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Validators\Rules\PersonNameRule;
use HiEvents\Validators\Rules\RulesHelper;

class EditOrderRequest extends BaseRequest
{
    private ?OrderDomainObject $order = null;

    public function rules(): array
    {
        return [
            'email' => $this->isBoxOfficeOrder() ? RulesHelper::OPTIONAL_EMAIL : RulesHelper::REQUIRED_EMAIL,
            'first_name' => [...RulesHelper::REQUIRED_STRING, new PersonNameRule($this->order()?->getFirstName())],
            'last_name' => [...($this->isBoxOfficeOrder() ? RulesHelper::STRING : RulesHelper::REQUIRED_STRING), new PersonNameRule($this->order()?->getLastName())],
            'notes' => RulesHelper::OPTIONAL_TEXT_MEDIUM_LENGTH,
        ];
    }

    private function isBoxOfficeOrder(): bool
    {
        return $this->order()?->isBoxOfficeOrder() ?? false;
    }

    private function order(): ?OrderDomainObject
    {
        return $this->order ??= app(OrderRepositoryInterface::class)->findFirstWhere([
            'id' => $this->route('order_id'),
            'event_id' => $this->route('event_id'),
        ]);
    }
}
