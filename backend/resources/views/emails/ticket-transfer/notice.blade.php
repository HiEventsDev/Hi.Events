@php /** @var string $heading */ @endphp
@php /** @var string $summary */ @endphp
@php /** @var bool $asksToReportIfUnexpected */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp

@php /** @see \HiEvents\Mail\TicketTransfer\TicketTransferNoticeMail */ @endphp

<x-mail::message>
# {{ $heading }}

{{ $summary }}

@if($asksToReportIfUnexpected)
{{ __('If you did not expect this, please contact the event organizer straight away.') }}

@if($eventSettings->getSupportEmail())
{{ __('Contact: :email', ['email' => $eventSettings->getSupportEmail()]) }}
@endif
@endif

{{ __('Thank you') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
