@php /** @var string $keyName */ @endphp
@php /** @var string $accountName */ @endphp
@php /** @var string $apiKeysUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('An administrator of :accountName revoked your API key ":keyName". Integrations that use it can no longer connect.', ['accountName' => $accountName, 'keyName' => $keyName]) }}

{{ __('If you still need API access, create a new key and update your integration.') }}

<x-mail::button :url="$apiKeysUrl">
{{ __('Manage API keys') }}
</x-mail::button>

{{ __('Thanks,') }}
</x-mail::message>
