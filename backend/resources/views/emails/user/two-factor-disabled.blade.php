@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var bool $resetByAdministrator */ @endphp
@php /** @var string $securityUrl */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $user->getFirstName()]) }},

@if($resetByAdministrator)
{{ __('An administrator has reset two-factor authentication on your :appName account. You can now sign in with just your password.', ['appName' => config('app.name')]) }}
@else
{{ __('Two-factor authentication has been turned off for your :appName account.', ['appName' => config('app.name')]) }}
@endif

{{ __('We recommend turning it back on to keep your account secure.') }}

<x-mail::button :url="$securityUrl">
{{ __('Turn on two-factor authentication') }}
</x-mail::button>

{{ __('If you did not make this change, please reset your password immediately and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
