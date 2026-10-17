@php /** @var string $ownerEmail */ @endphp
@php /** @var string $accountName */ @endphp
@php /** @var string $signedInAt */ @endphp
@php /** @var string $settingsUrl */ @endphp
@php /** @var bool $recipientIsOwner */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __(':ownerEmail, the owner of :accountName, signed in with a password and two-factor authentication instead of single sign-on at :time (UTC).', ['ownerEmail' => $ownerEmail, 'accountName' => $accountName, 'time' => $signedInAt]) }}

{{ __('The account owner can always sign in this way so the account can be recovered if your identity provider is unavailable.') }}

<x-mail::button :url="$settingsUrl">
{{ __('Review single sign-on settings') }}
</x-mail::button>

@if($recipientIsOwner)
{{ __('If this wasn\'t you, change your password and set up two-factor authentication again with a new authenticator straight away.') }}
@else
{{ __('If you did not expect this, contact the account owner and support.') }}
@endif

{{ __('Thanks,') }}
</x-mail::message>
