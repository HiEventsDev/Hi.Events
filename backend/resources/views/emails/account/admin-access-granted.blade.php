@php /** @var string $newAdminEmail */ @endphp
@php /** @var string|null $grantedByEmail */ @endphp
@php /** @var bool $isInvitation */ @endphp
@php /** @var string $teamUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

@if($isInvitation)
{{ __(':newAdminEmail was invited as an admin of your :appName account. They get access once they accept. Admins can manage every event, the team, payments and account settings.', ['newAdminEmail' => $newAdminEmail, 'appName' => config('app.name')]) }}
@else
{{ __(':newAdminEmail now has admin access to your :appName account. Admins can manage every event, the team, payments and account settings.', ['newAdminEmail' => $newAdminEmail, 'appName' => config('app.name')]) }}
@endif

@if($grantedByEmail)
{{ __('This change was made by :grantedByEmail.', ['grantedByEmail' => $grantedByEmail]) }}
@else
{{ __('They joined automatically by signing in through your single sign-on connection, which gives new members the admin role.') }}
@endif

<x-mail::button :url="$teamUrl">
{{ __('Review your team') }}
</x-mail::button>

@if($isInvitation)
{{ __('If you did not expect this, remove the invitation and contact support.') }}
@else
{{ __('If you did not expect this, remove their access and contact support.') }}
@endif

{{ __('Thanks,') }}
</x-mail::message>
