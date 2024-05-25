@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $ticketUrl */ @endphp

@php /** @see \HiEvents\Mail\Order\OrderCancelled */ @endphp

<x-mail::message>
Hello,

Your order for <b>{{$event->getTitle()}}</b> has been cancelled.
<br>
<br>
Order #: <b>{{$order->getPublicId()}}</b>
<br>
<br>
If you have any questions or need assistance, please respond to this email.
<br><br>
Thank you,<br>
{{config('app.name')}}

{!! $eventSettings->getGetEmailFooterHtml() !!}

</x-mail::message>
