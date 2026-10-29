@php use Carbon\Carbon; use HiEvents\Helper\DateHelper; @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var array<int, array{attendee: \HiEvents\DomainObjects\AttendeeDomainObject, occurrence: ?\HiEvents\DomainObjects\EventOccurrenceDomainObject, startDate: ?string, endDate: ?string, venueName: ?string, addressString: ?string, ticketUrl: string}> $tickets */ @endphp
@php /** @var \HiEvents\Services\Domain\Lineup\DTO\EmailLineupHighlightsDTO|null $lineup */ @endphp
@php /** @see \HiEvents\Mail\Attendee\AttendeeTicketMail */ @endphp

@php
    $tz = $event->getTimezone();

    $formatDateTime = static fn(?string $utc) => $utc
        ? (new Carbon(DateHelper::convertFromUTC($utc, $tz)))->format('D, M j, Y · g:i A')
        : null;
    $formatTime = static fn(?string $utc) => $utc
        ? (new Carbon(DateHelper::convertFromUTC($utc, $tz)))->format('g:i A')
        : null;
@endphp

<x-mail::message>
# {{ __('You\'re going to') }} {{ $event->getTitle() }}! 🎉

@if($order->isOrderAwaitingOfflinePayment())
<div style="border-radius: 4px; background-color: #f8d7da; color: #842029; margin-bottom: 1.5rem; padding: 1rem;">
<p>
{{ __('ℹ️ Your order is pending payment. Tickets have been issued but will not be valid until payment is received.') }}
</p>
</div>
@endif

{{ __('Please find your ticket details below.') }}

@foreach($tickets as $ticket)
@php
    $attendee = $ticket['attendee'];
    $occurrence = $ticket['occurrence'];
    $venueName = $ticket['venueName'];
    $addressString = $ticket['addressString'];
    $displayStart = $ticket['startDate'];
    $displayEnd = $ticket['endDate'];

    $startFormatted = $formatDateTime($displayStart);
    $endFormatted = null;
    if ($displayStart && $displayEnd) {
        $sameDay = substr($displayStart, 0, 10) === substr($displayEnd, 0, 10);
        $endFormatted = $sameDay ? $formatTime($displayEnd) : $formatDateTime($displayEnd);
    }

    $productTitle = $attendee->getProduct()?->getTitle();
@endphp
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
@if($attendee->getSeatLabel())
<strong>{{ __('Seat:') }}</strong> {{ $attendee->getSeatLabel() }}<br>
@endif
<strong>{{ __('Attendee:') }}</strong> {{ trim($attendee->getFirstName() . ' ' . $attendee->getLastName()) }}
</div>
@endif

<x-mail::button :url="$ticket['ticketUrl']">
{{ __('View Ticket') }}
</x-mail::button>

@endforeach
@include('emails.partials.lineup-highlights', ['lineup' => $lineup])

{{ __('If you have any questions or need assistance, please reply to this email or contact the event organizer') }}
{{ __('at') }} <a href="mailto:{{$eventSettings->getSupportEmail()}}">{{$eventSettings->getSupportEmail()}}</a>.

{{ __('Best regards,') }}<br>
{{ $organizer->getName() ?: config('app.name') }}

</x-mail::message>
