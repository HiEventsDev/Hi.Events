@php /** @var string $ownerEmail */ @endphp
@php /** @var string $accountName */ @endphp
@php /** @var string $signedInAt */ @endphp
@php /** @var string $settingsUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __(':ownerEmail, the owner of :accountName, signed in with a password and two-factor authentication instead of single sign-on at :time (UTC).', ['ownerEmail' => $ownerEmail, 'accountName' => $accountName, 'time' => $signedInAt]) }}

{{ __('The account owner can always sign in this way so the account can be recovered if your identity provider is unavailable.') }}

<x-mail::button :url="$settingsUrl">
{{ __('Review single sign-on settings') }}
</x-mail::button>

{{ __('If you did not expect this, contact the account owner and support.') }}

{{ __('Thanks,') }}
</x-mail::message>
