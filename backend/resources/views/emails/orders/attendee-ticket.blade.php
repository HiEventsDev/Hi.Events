@php use Carbon\Carbon; use HiEvents\Helper\DateHelper; @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\AttendeeDomainObject $attendee */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventOccurrenceDomainObject|null $occurrence */ @endphp
@php /** @var string $ticketUrl */ @endphp
@php /** @see \HiEvents\Mail\Attendee\AttendeeTicketMail */ @endphp

@php
    $tz = $event->getTimezone();
    $displayStart = $occurrence?->getStartDate() ?? $event->getStartDate();
    $displayEnd = $occurrence?->getEndDate() ?? $event->getEndDate();

    $formatDateTime = static fn(?string $utc) => $utc
        ? (new Carbon(DateHelper::convertFromUTC($utc, $tz)))->format('D, M j, Y · g:i A')
        : null;
    $formatTime = static fn(?string $utc) => $utc
        ? (new Carbon(DateHelper::convertFromUTC($utc, $tz)))->format('g:i A')
        : null;

    $startFormatted = $formatDateTime($displayStart);
    $endFormatted = null;
    if ($displayStart && $displayEnd) {
        // Same day → show just the end time; cross-day → show the full end timestamp.
        $sameDay = substr($displayStart, 0, 10) === substr($displayEnd, 0, 10);
        $endFormatted = $sameDay ? $formatTime($displayEnd) : $formatDateTime($displayEnd);
    }

    $venueName = $eventSettings->getLocationDetails()['venue_name'] ?? null;
    $addressString = $eventSettings->getIsOnlineEvent() ? null : $eventSettings->getAddressString();
    $productTitle = $attendee->getProduct()?->getTitle();
@endphp

<x-mail::message>
# {{ __('You\'re going to') }} {{ $event->getTitle() }}! 🎉
<br>
<br>
@if($order->isOrderAwaitingOfflinePayment())
<div style="border-radius: 4px; background-color: #f8d7da; color: #842029; margin-bottom: 1.5rem; padding: 1rem;">
<p>
{{ __('ℹ️ Your order is pending payment. Tickets have been issued but will not be valid until payment is received.') }}
</p>
</div>
@endif

{{ __('Please find your ticket details below.') }}

@if($startFormatted || $venueName || $addressString || $productTitle)
<div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin: 16px 0; line-height: 1.6;">
@if($startFormatted)
<strong>{{ __('Date & Time:') }}</strong> {{ $startFormatted }}@if($endFormatted) – {{ $endFormatted }}@endif<br>
@if($occurrence?->getLabel())
<strong>{{ __('Session:') }}</strong> {{ $occurrence->getLabel() }}<br>
@endif
@endif
@if($venueName || $addressString)
<strong>{{ __('Location:') }}</strong> {{ trim(($venueName ? $venueName . ($addressString ? ', ' : '') : '') . ($addressString ?? '')) }}<br>
@endif
@if($productTitle)
<strong>{{ __('Ticket:') }}</strong> {{ $productTitle }}<br>
@endif
<strong>{{ __('Attendee:') }}</strong> {{ trim($attendee->getFirstName() . ' ' . $attendee->getLastName()) }}
</div>
@endif

<x-mail::button :url="$ticketUrl">
{{ __('View Ticket') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, please reply to this email or contact the event organizer') }}
{{ __('at') }} <a href="mailto:{{$eventSettings->getSupportEmail()}}">{{$eventSettings->getSupportEmail()}}</a>.

{{ __('Best regards,') }}<br>
{{ $organizer->getName() ?: config('app.name') }}

</x-mail::message>
