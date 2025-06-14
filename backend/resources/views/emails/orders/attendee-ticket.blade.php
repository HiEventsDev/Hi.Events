@php use HiEvents\Helper\DateHelper; @endphp
@php /** @uses \HiEvents\Mail\Order\OrderSummary */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\AttendeeDomainObject $attendee */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp

@php /** @var string $ticketUrl */ @endphp
@php /** @see \HiEvents\Mail\Attendee\AttendeeTicketMail */ @endphp

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

<x-mail::button :url="$ticketUrl">
{{ __('View Ticket') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, please reply to this email or contact the event organizer') }}
{{ __('at') }} <a href="mailto:{{$eventSettings->getSupportEmail()}}">{{$eventSettings->getSupportEmail()}}</a>.

{{ __('Best regards,') }}<br>
{{ config('mail.from.name') }}

<script type="application/ld+json">
        {
  "@context": "http://schema.org",
  "@type": "EventReservation",
  "reservationNumber": "{{ $attendee->getPublicId() }}",
  "reservationStatus": "http://schema.org/Confirmed",
  "underName": {
    "@type": "Person",
    "name": "{{ $attendee->getFirstName() }} {{ $attendee->getLastName() }}"
  },
  "reservationFor": {
    "@type": "Event",
    "name": "{{ $event->getTitle() }}",
    "performer": {
      "@type": "Organization",
      "name": "{{ $organizer->getName() }}",
    },
    "startDate": "{{ DateHelper::convertFromUTC($event->getStartDate(), $event->getTimezone()) }}",

    @if($event->getEndDate())
      "endDate": "{{ DateHelper::convertFromUTC($event->getEndDate(), $event->getTimezone()) }}",
    @endif

    @if ($eventSettings->getLocationDetails())
    "location": {
      "@type": "Place",
      "name": "{{ $eventSettings->getAddress()->venue_name }}",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "{{ $eventSettings->getAddress()->address_line_1 . ' ' . $eventSettings->getAddress()->address_line_2 }}",
        "addressLocality": "{{ $eventSettings->getAddress()->city }}",
        "addressRegion": "{{ $eventSettings->getAddress()->state_or_region }}",
        "postalCode": "{{ $eventSettings->getAddress()->zip_or_postal_code }}",
        "addressCountry": "{{ $eventSettings->getAddress()->country }}"
      }
    }
  },
  @endif

  "ticketToken": "qrCode:{{ $attendee->getPublicId() }}",
  "ticketNumber": "{{ $attendee->getPublicId() }}",
  "ticketPrintUrl": "{{ $ticketUrl }}",
}
</script>
</x-mail::message>
