<?php

namespace HiEvents\Http\Request\SelfService;

use HiEvents\DomainObjects\Enums\PhoneCollection;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Validators\Rules\MobilePhoneNumberRule;
use HiEvents\Validators\Rules\PersonNameRule;

class EditOrderPublicRequest extends BaseRequest
{
    private ?OrderDomainObject $order = null;

    private ?PhoneCollection $phoneCollection = null;

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255', new PersonNameRule($this->order()?->getFirstName())],
            'last_name' => ['sometimes', 'string', 'max:255', new PersonNameRule($this->order()?->getLastName())],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => $this->phoneCollection()->isCollected()
                ? ['sometimes', ...MobilePhoneNumberRule::rules($this->phoneCollection() === PhoneCollection::REQUIRED)]
                : [],
        ];
    }

    public function collectedPhone(): ?string
    {
        return $this->has('phone') && $this->phoneCollection()->isCollected() ? (string) $this->input('phone') : null;
    }

    public function messages(): array
    {
        return [
            'first_name.string' => __('First name must be a string'),
            'first_name.max' => __('First name must not exceed 255 characters'),
            'last_name.string' => __('Last name must be a string'),
            'last_name.max' => __('Last name must not exceed 255 characters'),
            'email.email' => __('Email must be a valid email address'),
            'email.max' => __('Email must not exceed 255 characters'),
        ];
    }

    private function phoneCollection(): PhoneCollection
    {
        if ($this->phoneCollection === null) {
            /** @var EventSettingDomainObject|null $settings */
            $settings = app(EventSettingsRepositoryInterface::class)->findFirstWhere([
                'event_id' => $this->route('event_id'),
            ]);

            $this->phoneCollection = PhoneCollection::from($settings?->getPhoneCollection() ?? PhoneCollection::OFF->value);
        }

        return $this->phoneCollection;
    }

    private function order(): ?OrderDomainObject
    {
        return $this->order ??= app(OrderRepositoryInterface::class)->findFirstWhere([
            'short_id' => $this->route('order_short_id'),
            'event_id' => $this->route('event_id'),
        ]);
    }
}
