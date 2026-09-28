@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\AttendeeDomainObject $attendee */ @endphp
@php /** @var string $toppedUpAmount */ @endphp
@php /** @var string $newBalance */ @endphp
@php /** @var string $walletUrl */ @endphp

@php /** @see \HiEvents\Mail\Cashless\CashlessTopupConfirmationMail */ @endphp

<x-mail::message>
# {{ __('Your cashless balance is topped up') }}

{{ __('Hello :name,', ['name' => $attendee->getFirstName()]) }}

{{ __('We have added **:amount** to the cashless balance attached to your ticket for **:event**.', ['amount' => $toppedUpAmount, 'event' => $event->getTitle()]) }}

{{ __('New balance') }}: **{{ $newBalance }}**

{{ __('Show the QR code on your ticket at any bar or stand to pay — no cash needed.') }}

<x-mail::button :url="$walletUrl">
{{ __('View my balance') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, please respond to this email.') }}

{{ __('Thank you') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
