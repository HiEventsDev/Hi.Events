@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp

<x-mail::message>
    Hello,

    Your recent order for <b>{{$event->getTitle()}}</b> was not successful. The order expired while you were completing
    the payment.
    We have issued a refund for the order.

    We apologize for the inconvenience. If you have any questions or need assistance, feel free to reach out to our
    support team
    at {{ $supportEmail ?? 'hello@hi.events' }}.

    <x-mail::button :url="$event->getEventUrl()">
        View Event Page
    </x-mail::button>

    Best regards,
    Hi.Events
</x-mail::message>
