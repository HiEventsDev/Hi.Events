@php /** @var string $appName */ @endphp
@php /** @var string $redirectHost */ @endphp
@php /** @var string $accountName */ @endphp
@php /** @var string $accessLabel */ @endphp
@php /** @var string $connectedAppsUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('An app was connected to your :appName account. It can use your access to :accountName until you disconnect it.', ['appName' => config('app.name'), 'accountName' => $accountName]) }}

<x-mail::panel>
**{{ __('App') }}:** {{ $appName }}<br>
**{{ __('Returns to') }}:** {{ $redirectHost }}<br>
**{{ __('Access') }}:** {{ $accessLabel }}
</x-mail::panel>

<x-mail::button :url="$connectedAppsUrl">
{{ __('Review connected apps') }}
</x-mail::button>

{{ __('If you did not connect this app, disconnect it now, change your password and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
