@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\AccountDomainObject $account */ @endphp
@php /** @var array $verdict */ @endphp
@php /** @var string $reviewUrl */ @endphp
@php /** @var bool $eventStillLive */ @endphp

<x-mail::message>
@if($eventStillLive)
{{ __('An event you previously approved has been flagged as potential spam after a content change. It is still live. Review it and unpublish it if needed.') }}
@else
{{ __('An event has been flagged as potential spam and unpublished pending manual review.') }}
@endif

## {{ __('Event Details') }}

**{{ __('Title') }}:** {{ $event->getTitle() }} (ID: {{ $event->getId() }})

**{{ __('Organizer') }}:** {{ $event->getOrganizer()?->getName() }}

**{{ __('Account') }}:** {{ $account->getName() }} (ID: {{ $account->getId() }})

## {{ __('Verdict') }}

**{{ __('Confidence') }}:** {{ number_format(($verdict['confidence'] ?? 0) * 100) }}%

@foreach(($verdict['reasons'] ?? []) as $reason)
- {{ $reason }}
@endforeach

<x-mail::button :url="$reviewUrl">
{{ __('Review Event') }}
</x-mail::button>

{{ __('Thank you') }}

</x-mail::message>
