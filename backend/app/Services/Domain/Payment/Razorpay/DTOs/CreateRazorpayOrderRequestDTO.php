<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Support\Arrayable;

class CreateRazorpayOrderRequestDTO implements Arrayable
{
    public function __construct(
        public readonly MoneyValue $amount,
        public readonly string $currencyCode,
        public readonly AccountDomainObject $account,
        public readonly OrderDomainObject $order,
        public readonly ?AccountVatSettingDomainObject $vatSettings = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: $data['amount'],
            currencyCode: $data['currencyCode'],
            account: $data['account'],
            order: $data['order'],
            vatSettings: $data['vatSettings'] ?? null,
        );
    }

    public function toArray(array $except = []): array
    {
        $data = [
            'amount' => $this->amount,
            'currencyCode' => $this->currencyCode,
            'account' => in_array('account', $except) ? '[object]' : $this->account->toArray(),
            'order' => in_array('order', $except) ? '[object]' : $this->order->toArray(),
            'vatSettings' => $this->vatSettings?->toArray(),
        ];

        foreach ($except as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}