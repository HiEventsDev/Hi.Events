@php /** @uses /backend/app/Mail/OrderSummary.php */ @endphp
@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $ticketUrl */ @endphp
@php /** @see \HiEvents\Mail\Attendee\AttendeeTicketMail */ @endphp

<x-mail::message>
# You're going to {{ $event->getTitle() }}! 🎉
<br>
<br>

Please find your ticket details below.

<x-mail::button :url="$ticketUrl">
    View Ticket
</x-mail::button>

If you have any questions or need assistance, please reply to this email or contact the event organizer
at <a href="mailto:{{$eventSettings->getSupportEmail()}}">{{$eventSettings->getSupportEmail()}}</a>.

Best regards,
<br>
{{config('app.name')}}
</x-mail::message>
