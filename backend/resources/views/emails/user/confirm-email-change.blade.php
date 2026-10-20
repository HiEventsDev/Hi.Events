@php /** @var string $newEmail */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('You have requested to change your email address to :pendingEmail. Please click the link below to confirm this change.', ['pendingEmail' => $newEmail]) }}

<a href="{{ $link }}">{{ __('Confirm email change') }}</a>

{{ __('If you did not request this change, you can safely ignore this email.') }}

{{ __('Thanks,') }}
</x-mail::message>
