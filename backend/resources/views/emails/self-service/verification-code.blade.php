@php /** @var string $code */ @endphp
@php /** @var int $validForMinutes */ @endphp

<x-mail::message>
# {{ __('Your verification code') }}

{{ __('Enter this code to confirm it is you before making changes to your tickets:') }}

<x-mail::panel>
{{ $code }}
</x-mail::panel>

{{ __('This code expires in :minutes minutes.', ['minutes' => $validForMinutes]) }}

{{ __('If you did not request this, you can ignore this email. Nobody can change your tickets without this code.') }}

{{ __('Thank you') }}
</x-mail::message>
