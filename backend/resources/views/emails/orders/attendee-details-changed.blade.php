@php /** @var string $ticketTitle */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var array $changedFields */ @endphp

<x-mail::message>
# {{ __('Ticket Details Changed') }}

{{ __('The details on your ticket for **:eventName** have been updated.', ['eventName' => $event->getTitle()]) }}

**{{ __('Ticket') }}**: {{ $ticketTitle }}

## {{ __('What Changed') }}

@foreach($changedFields as $field => $change)
- **{{ $field }}**: {{ $change['old'] }} → {{ $change['new'] }}
@endforeach

{{ __('If you did not make this change, please contact the event organizer immediately.') }}

---

{{ __('Event Organizer: :organizerName', ['organizerName' => $organizer->getName()]) }}

@if($eventSettings->getSupportEmail())
{{ __('Contact: :email', ['email' => $eventSettings->getSupportEmail()]) }}
@endif

{{ __('Thanks,') }}<br>
{{ $organizer->getName() }}
</x-mail::message>
