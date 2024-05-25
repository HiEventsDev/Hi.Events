@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @see \HiEvents\Mail\Order\PaymentSuccessButOrderExpiredMail */ @endphp

<x-mail::message>
Hello,

<p>
Your recent order for <b>{{$event->getTitle()}}</b> was not successful. The order expired while you were completing
the payment.
We have issued a refund for the order.
</p>

<p>
We apologize for the inconvenience. If you have any questions or need assistance, feel free to reach us
at <a href="mailto:{{$organizer->getEmail()}}">{{$organizer->getEmail()}}</a>.
</p>

<x-mail::button :url="$event->getEventUrl()">
    View Event Page
</x-mail::button>

Best regards,<br>
Hi.Events

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
