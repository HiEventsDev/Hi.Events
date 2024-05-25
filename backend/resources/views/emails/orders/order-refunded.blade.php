@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\Values\MoneyValue $refundAmount */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp

@php /** @see \HiEvents\Mail\Order\OrderRefunded */ @endphp

<x-mail::message>
Hello,

You have received a refund of <b>{{$refundAmount}}</b> for the following event: <b>{{$event->getTitle()}}</b>.

Thank you

{!! $eventSettings->getGetEmailFooterHtml() !!}

</x-mail::message>
