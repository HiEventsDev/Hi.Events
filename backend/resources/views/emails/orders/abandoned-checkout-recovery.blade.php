@component('mail::message')
# Don't miss out!

Hi {{ $order->getFirstName() ?? 'there' }},

You started an order for **{{ $event->getTitle() }}** but didn't complete checkout.

Your selected tickets are still waiting for you!

@if($promoCode)
As a special offer, use promo code **{{ $promoCode }}** for a discount when you complete your order.
@endif

@component('mail::button', ['url' => $recoveryUrl, 'color' => 'primary'])
Complete Your Order
@endcomponent

If you have any questions, please contact us at {{ $eventSettings->getSupportEmail() ?? 'our support team' }}.

Thanks,<br>
{{ $organizer->getName() }}

<small>If you no longer wish to receive these reminders, simply ignore this email.</small>
@endcomponent
