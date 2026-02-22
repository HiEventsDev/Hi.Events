@php /** @var \HiEvents\DomainObjects\WaitlistEntryDomainObject $entry */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var ?string $productName */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $eventUrl */ @endphp

@php /** @see \HiEvents\Mail\Waitlist\WaitlistOfferExpiredMail */ @endphp

<x-mail::message>
# {{ __('Your waitlist offer has expired') }}

{{ __('Hello') }},

@if($productName)
{{ __('Unfortunately, your waitlist offer for **:product** for the event **:event** has expired.', ['product' => $productName, 'event' => $event->getTitle()]) }}
@else
{{ __('Unfortunately, your waitlist offer for the event **:event** has expired.', ['event' => $event->getTitle()]) }}
@endif

{{ __('If you are still interested, you may rejoin the waitlist from the event page.') }}

<x-mail::button :url="$eventUrl">
{{ __('View Event') }}
</x-mail::button>

{{ __('If you have any questions or need assistance, please respond to this email.') }}

{{ __('Thank you') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
