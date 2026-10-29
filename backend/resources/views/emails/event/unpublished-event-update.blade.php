@php /** @var string $appName */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('There is an update about a booking made with your email address on :appName.', ['appName' => $appName]) }}

{{ __('The organizer has not published this event yet. You will receive the full details once it is published.') }}

{{ __('If you were not expecting this email, you can safely ignore it.') }}

{{ __('Thank you') }},<br>
{{ $appName }}
</x-mail::message>
