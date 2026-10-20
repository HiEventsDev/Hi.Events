@php /** @var string $providerName */ @endphp
@php /** @var bool $connected */ @endphp
@php /** @var string $securityUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

@if($connected)
{{ __('You can now sign in to your :appName account with :provider.', ['appName' => config('app.name'), 'provider' => $providerName]) }}
@else
{{ __('You can no longer sign in to your :appName account with :provider.', ['appName' => config('app.name'), 'provider' => $providerName]) }}
@endif

<x-mail::button :url="$securityUrl">
{{ __('Review sign-in methods') }}
</x-mail::button>

{{ __('If you did not make this change, please reset your password immediately and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
