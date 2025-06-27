@php /** @var \HiEvents\DomainObjects\OrderDomainObject $order */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $eventUrl */ @endphp

@php /** @see \HiEvents\Mail\Order\OrderFailed */ @endphp

<x-mail::message>
{{ __('Hello') }},

{{ __('Your recent order for') }} <b>{{$event->getTitle()}}</b> {{ __('was not successful.') }}

<x-mail::button :url="$eventUrl">
{{ __('View Event Homepage') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, feel free to reach out to our support team') }}
{{ __('at') }} {{ $supportEmail ?? 'hello@hi.events' }}.

{{ __('Best regards') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
