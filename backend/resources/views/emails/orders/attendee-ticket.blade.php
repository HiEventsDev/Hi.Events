@php use TicketKitten\Helper\Currency @endphp

@php /** @uses /backend/app/Mail/OrderSummary.php */ @endphp
@php /** @var \TicketKitten\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \TicketKitten\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
# You're going to {{ $event->getTitle() }}! 🎉
<br>
<br>

Please find your ticket details below.

<x-mail::button :url="$event->getEventUrl()">
View Ticket
</x-mail::button>

If you have any questions or need assistance, feel free to reach out to our friendly support team
at {{ $supportEmail ?? 'hello@hi.events' }}.

Best regards,
<br>
TicketKitten.com
</x-mail::message>
