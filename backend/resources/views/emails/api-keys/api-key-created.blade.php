@php /** @var string $keyName */ @endphp
@php /** @var string $accountName */ @endphp
@php /** @var string $accessLabel */ @endphp
@php /** @var string|null $expiresAt */ @endphp
@php /** @var string $apiKeysUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('A new API key was created on your :appName account. Anyone with this key can use the API with your access to :accountName.', ['appName' => config('app.name'), 'accountName' => $accountName]) }}

<x-mail::panel>
**{{ __('Name') }}:** {{ $keyName }}<br>
**{{ __('Access') }}:** {{ $accessLabel }}<br>
**{{ __('Expires') }}:** {{ $expiresAt ?? __('Never') }}
</x-mail::panel>

<x-mail::button :url="$apiKeysUrl">
{{ __('Review API keys') }}
</x-mail::button>

{{ __('If you did not create this key, revoke it now, change your password and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
