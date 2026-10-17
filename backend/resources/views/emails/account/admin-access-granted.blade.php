@php /** @var string $newAdminEmail */ @endphp
@php /** @var string|null $grantedByEmail */ @endphp
@php /** @var string $teamUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __(':newAdminEmail now has admin access to your :appName account. Admins can manage every event, the team, payments and account settings.', ['newAdminEmail' => $newAdminEmail, 'appName' => config('app.name')]) }}

@if($grantedByEmail)
{{ __('This change was made by :grantedByEmail.', ['grantedByEmail' => $grantedByEmail]) }}
@else
{{ __('They joined automatically by signing in through your single sign-on connection, which gives new members the admin role.') }}
@endif

<x-mail::button :url="$teamUrl">
{{ __('Review your team') }}
</x-mail::button>

{{ __('If you did not expect this, remove their access and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
