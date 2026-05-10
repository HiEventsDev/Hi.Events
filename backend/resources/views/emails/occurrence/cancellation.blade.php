@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\EventOccurrenceDomainObject $occurrence */ @endphp
@php /** @var \HiEvents\DomainObjects\OrganizerDomainObject $organizer */ @endphp
@php /** @var \HiEvents\DomainObjects\EventSettingDomainObject $eventSettings */ @endphp
@php /** @var string $formattedDate */ @endphp
@php /** @var string $eventUrl */ @endphp
@php /** @var bool $refundOrders */ @endphp

@php /** @see \HiEvents\Mail\Occurrence\OccurrenceCancellationMail */ @endphp

<x-mail::message>
# {{ $event->getTitle() }}

{{ __('Hello') }},

{{ __('We\'re sorry to let you know that **:event** scheduled for **:date** has been cancelled.', ['event' => $event->getTitle(), 'date' => $formattedDate]) }}

@if($refundOrders)
{{ __('A refund for your order will be processed automatically. Please allow a few business days for the refund to appear on your statement.') }}
@else
{{ __('If you have any questions about your order, please respond to this email.') }}
@endif

<x-mail::button :url="$eventUrl">
{{ __('View Event') }}
</x-mail::button>

{{ __('Thank you') }},<br>
{{ $organizer->getName() ?: config('app.name') }}

{!! $eventSettings->getGetEmailFooterHtml() !!}
</x-mail::message>
