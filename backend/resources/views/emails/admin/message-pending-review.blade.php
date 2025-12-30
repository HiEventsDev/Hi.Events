@php /** @var \HiEvents\DomainObjects\MessageDomainObject $message */ @endphp
@php /** @var \HiEvents\DomainObjects\EventDomainObject $event */ @endphp
@php /** @var \HiEvents\DomainObjects\AccountDomainObject $account */ @endphp
@php /** @var array $failures */ @endphp
@php /** @var string $reviewUrl */ @endphp

<x-mail::message>
{{ __('A message has been flagged for review due to eligibility check failures.') }}

## {{ __('Message Details') }}

**{{ __('Subject') }}:** {{ $message->getSubject() }}

**{{ __('Account') }}:** {{ $account->getName() }} (ID: {{ $account->getId() }})

**{{ __('Event') }}:** {{ $event->getTitle() }} (ID: {{ $event->getId() }})

**{{ __('Message ID') }}:** {{ $message->getId() }}

## {{ __('Eligibility Failures') }}

@foreach($failures as $failure)
@php
    $failureLabels = [
        'stripe_not_connected' => __('Stripe payment account not connected'),
        'no_paid_orders' => __('No completed paid orders on this account'),
        'event_too_new' => __('Event was created less than 24 hours ago'),
    ];
@endphp
- {{ $failureLabels[$failure] ?? $failure }}
@endforeach

<x-mail::button :url="$reviewUrl">
{{ __('Review Message') }}
</x-mail::button>

{{ __('Thank you') }}

</x-mail::message>
