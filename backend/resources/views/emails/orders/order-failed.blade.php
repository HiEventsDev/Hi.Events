@php /** @var \TicketKitten\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \TicketKitten\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
Hello,

Your recent order for <b>{{$event->getTitle()}}</b> was not successful.

<x-mail::button :url="''">
View Event Page
</x-mail::button>

If you have any questions or need assistance, feel free to reach out to our support team
at {{ $supportEmail ?? 'hello@hi.events' }}.

Best regards,
TicketKitten.com
</x-mail::message>
