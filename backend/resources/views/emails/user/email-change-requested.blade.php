@php /** @var string $securityUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('Someone asked to change the email address on your :appName account. The change only takes effect once the new address is confirmed.', ['appName' => config('app.name')]) }}

{{ __('If this was not you, change your password now and cancel the pending change from your profile.') }}

<x-mail::button :url="$securityUrl">
{{ __('Review account security') }}
</x-mail::button>

{{ __('Thanks,') }}
</x-mail::message>
