@php use Carbon\Carbon; use HiEvents\Helper\Currency; use HiEvents\Helper\DateHelper; @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $orderUrl */ @endphp
@php /** @see \HiEvents\Mail\Order\OrderSummary */ @endphp

<x-mail::message>
# {{ __('Your Order is Confirmed! ') }} 🎉

@if($order->isOrderAwaitingOfflinePayment() === false)
<p>
{{ __('Congratulations! Your order for :eventTitle on :eventDate at :eventTime was successful. Please find your order details below.', ['eventTitle' => $event->getTitle(), 'eventDate' => (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('F j, Y'), 'eventTime' => (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('g:i A')]) }}
</p>
@else
<div>
<p>
{{ __('Your order is pending payment. Tickets have been issued but will not be valid until payment is received.') }}
</p>

<div style="border-radius: 4px; background-color: #d7e8f8; color: #204e84; margin-bottom: 1.5rem; padding: 1rem;">
<h2>{{ __('Payment Instructions') }}</h2>
{{ __('Please follow the instructions below to complete your payment.') }}
{!! $eventSettings->getProcessedOfflinePaymentInstructions([
    'order_short_id' => $order->getShortId(),
    'order_public_id' => $order->getPublicId(),
    'order_first_name' => $order->getFirstName(),
    'order_last_name' => $order->getLastName(),
    'order_email' => $order->getEmail(),
    'order_total_gross' => $order->getTotalGross(),
    'order_currency' => $event->getCurrency(),
    'order_items' => $order->getOrderItems(),
    'client_language' => app()->getLocale()
]) !!}
</div>
</div>
@endif

<p>

## {{ __('Event Details') }}
**{{ __('Event Name:') }}** {{ $event->getTitle() }}
    <br>
**{{ __('Date & Time:') }}** {{ (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('F j, Y') }} at {{ (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('g:i A') }}

</p>

## {{ __('Order Summary') }}
- **{{ __('Order Number:') }}** {{ $order->getPublicId() }}
- **{{ __('Total Amount:') }}** {{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}

<x-mail::button :url="$orderUrl">
    {{ __('View Order Summary & Tickets') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, feel free to reach out to our friendly support team at') }} <a href="mailto:{{ $organizer->getEmail() }}">{{ $organizer->getEmail() }}</a>.

## {{ __('What\'s Next?') }}
- **{{ __('Download Tickets:') }}** {{ __('Please download your tickets from the order summary page.') }}
- **{{ __('Prepare for the Event:') }}** {{ __('Make sure to note the event date, time, and location.') }}
- **{{ __('Stay Updated:') }}** {{ __('Keep an eye on your email for any updates from the event organizer.') }}

{{ __('Best regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
