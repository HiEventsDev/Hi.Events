@php use Carbon\Carbon;use HiEvents\Helper\Currency;use HiEvents\Helper\DateHelper; @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var string $orderUrl */ @endphp

<x-mail::message>
# Your Order is Confirmed! 🎉
<p>
Congratulations! Your order for <b>{{ $event->getTitle() }}</b> on
<b>{{ (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('F j, Y') }}</b>
at <b>{{ (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('g:i A') }}</b>
was successful. Please find your order details below.
</p>

<p>

## Event Details
- **Event Name:** {{ $event->getTitle() }}
- **Date & Time:** {{ (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('F j, Y') }} at {{ (new Carbon(DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone())))->format('g:i A') }}
</p>

## Order Summary
- **Order Number:** {{ $order->getPublicId() }}
- **Total Amount:** {{ Currency::format($order->getTotalGross(), $event->getCurrency()) }}

<x-mail::button :url="$orderUrl">
    View Order Summary & Tickets
</x-mail::button>

If you have any questions or need assistance, feel free to reach out to our friendly support team
at <a href="mailto:{{ $organizer->getEmail() }}">{{ $organizer->getEmail() }}</a>.

## What's Next?
- **Download Tickets:** Please download your tickets from the order summary page.
- **Prepare for the Event:** Make sure to note the event date, time, and location.
- **Stay Updated:** Keep an eye on your email for any updates from the event organizer.

Best regards,
<br>
{{ config('app.name') }}
</x-mail::message>
