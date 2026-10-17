@php /** @var string $keyName */ @endphp
@php /** @var string $accountName */ @endphp
@php /** @var string $expiresAt */ @endphp
@php /** @var string $apiKeysUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('Your API key ":keyName" for :accountName expires on :date. After that, integrations that use it can no longer connect.', ['keyName' => $keyName, 'accountName' => $accountName, 'date' => $expiresAt]) }}

{{ __('To avoid an interruption, create a new key and update your integration before then.') }}

<x-mail::button :url="$apiKeysUrl">
{{ __('Create a new key') }}
</x-mail::button>

{{ __('Thanks,') }}
</x-mail::message>
