@php use Carbon\Carbon; use HiEvents\Helper\Currency; use HiEvents\Helper\DateHelper; @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\EventOccurrenceDomainObject|null $occurrence */ @endphp
@php /** @var string $orderUrl */ @endphp

@php /** @see \HiEvents\Mail\Order\OrderSummary */ @endphp

@php
    $displayStart = $occurrence?->getStartDate() ?? $event->getStartDate();
    $displayDate = (new Carbon(DateHelper::convertFromUTC($displayStart, $event->getTimezone())))->format('F j, Y');
    $displayTime = (new Carbon(DateHelper::convertFromUTC($displayStart, $event->getTimezone())))->format('g:i A');
@endphp

<x-mail::message>
# {{ __('Your Order is Confirmed! ') }} 🎉

@if($order->isOrderAwaitingOfflinePayment() === false)

<p>
{{ __('Congratulations! Your order for :eventTitle on :eventDate at :eventTime was successful. Please find your order details below.', ['eventTitle' => $event->getTitle(), 'eventDate' => $displayDate, 'eventTime' => $displayTime]) }}
</p>

@else

<div>
<p>
{{ __('Your order is pending payment. Tickets have been issued but will not be valid until payment is received.') }}
</p>

<div style="border-radius: 4px; background-color: #d7e8f8; color: #204e84; margin-bottom: 1.5rem; padding: 1rem;">
<h2>{{ __('Payment Instructions') }}</h2>
{{ __('Please follow the instructions below to complete your payment.') }}
{!! $eventSettings->getOfflinePaymentInstructions() !!}
</div>
</div>

@endif

<p>

# {{ __('Event Details') }}
**{{ __('Event Name:') }}** {{ $event->getTitle() }}
    <br>
**{{ __('Date & Time:') }}** {{ __(':date at :time', ['date' => $displayDate, 'time' => $displayTime]) }}
@if($occurrence?->getLabel())
    <br>
**{{ __('Session:') }}** {{ $occurrence->getLabel() }}
@endif

</p>

@if($eventSettings->getPostCheckoutMessage() && $order->isOrderCompleted())
<p>

# {{ __('Additional Information') }}

{!! $eventSettings->getPostCheckoutMessage() !!}

</p>
@endif

# {{ __('Order Summary') }}
- **{{ __('Order Number:') }}** {{ $order->getPublicId() }}
- **{{ __('Total Amount:') }}** {{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}

<x-mail::button :url="$orderUrl">
    {{ __('View Order Summary & Tickets') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, please contact') }} <a href="mailto:{{ $organizer->getEmail() }}">{{ $organizer->getEmail() }}</a>.

{{ __('Best regards,') }}<br>
{{ $organizer->getName() ?: config('app.name') }}
</x-mail::message>
