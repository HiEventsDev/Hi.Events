@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $eventUrl */ @endphp

@php /** @see \HiEvents\Mail\Order\OrderFailed */ @endphp

<x-mail::message>
Hello,

Your recent order for <b>{{$event->getTitle()}}</b> was not successful.

<x-mail::button :url="$eventUrl">
    View Event Homepage
</x-mail::button>

If you have any questions or need assistance, feel free to reach out to our support team
at {{ $supportEmail ?? 'hello@hi.events' }}.

Best regards,
<br>
{{config('app.name')}}


{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
