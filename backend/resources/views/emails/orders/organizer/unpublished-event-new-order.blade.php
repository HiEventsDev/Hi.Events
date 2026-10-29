@php /** @var string $orderUrl */ @endphp
@php /** @var string $appName */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('A new order was placed on one of your events that is not published yet. Sign in to :appName to see the order details.', ['appName' => $appName]) }}

<x-mail::button :url="$orderUrl">
{{ __('View order') }}
</x-mail::button>

{{ __('Thank you') }},<br>
{{ $appName }}
</x-mail::message>
