@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $ticketTitle */ @endphp
@php /** @var string $maskedSenderEmail */ @endphp
@php /** @var string $claimUrl */ @endphp
@php /** @var string $expiresAtFormatted */ @endphp

@php /** @see \HiEvents\Mail\TicketTransfer\TicketTransferInvitationMail */ @endphp

<x-mail::message>
# {{ __('A ticket is waiting for you') }}

{{ __('**:email** wants to give you a **:ticket** ticket for **:event**.', ['email' => $maskedSenderEmail, 'ticket' => $ticketTitle, 'event' => $event->getTitle()]) }}

{{ __('Accept it to make the ticket yours. You will get your own ticket, and theirs will stop working.') }}

<x-mail::button :url="$claimUrl">
{{ __('Accept ticket') }}
</x-mail::button>

{{ __('This offer expires on :date.', ['date' => $expiresAtFormatted]) }}

{{ __('If you were not expecting this, you can ignore this email or decline from the same page. Nothing changes unless you accept.') }}

{{ __('Thank you') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
