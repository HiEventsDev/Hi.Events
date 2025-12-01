@php /** @var string $email */ @endphp
@php /** @var int $orderCount */ @endphp
@php /** @var string $ticketLookupUrl */ @endphp

<x-mail::message>
# {{ __('Your Tickets') }}

{{ __('Hello') }},

{{ __('We found :count order(s) associated with :email.', ['count' => $orderCount, 'email' => $email]) }}

{{ __('Click the button below to view your tickets and order details.') }}

<x-mail::button :url="$ticketLookupUrl">
{{ __('View My Tickets') }}
</x-mail::button>

{{ __('This link will expire in 24 hours.') }}

{{ __('If you did not request this, please ignore this email.') }}

{{ __('Thank you') }}
</x-mail::message>
