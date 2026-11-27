@php /** @var string $heading */ @endphp
@php /** @var string $summary */ @endphp
@php /** @var string|null $actionUrl */ @endphp
@php /** @var string|null $actionLabel */ @endphp
@php /** @var bool $isForOrganizer */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp

@php /** @see \HiEvents\Mail\TicketResale\TicketResaleNoticeMail */ @endphp

<x-mail::message>
# {{ $heading }}

{{ $summary }}

@if($actionUrl && $actionLabel)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

@if(!$isForOrganizer && $eventSettings->getSupportEmail())
{{ __('Contact: :email', ['email' => $eventSettings->getSupportEmail()]) }}
@endif

{{ __('Thank you') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
