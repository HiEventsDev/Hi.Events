@php use Carbon\Carbon; use HiEvents\Helper\Currency; use HiEvents\Helper\DateHelper; @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var string $orderUrl */ @endphp
@php /** @see \HiEvents\Mail\Order\OrderSummary */ @endphp

<x-mail::message>
# {{ __('Your Order is Confirmed! ') }} 🎉
<p>
{{ __('Congratulations! Your order for :eventTitle on :eventDate at :eventTime was successful. Please find your order details below.', ['eventTitle' => $event->getTitle(), 'eventDate' => (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('F j, Y'), 'eventTime' => (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('g:i A')]) }}
</p>

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

{{ __('Best regards,') }}
<br>
{{ config('app.name') }}
</x-mail::message>
