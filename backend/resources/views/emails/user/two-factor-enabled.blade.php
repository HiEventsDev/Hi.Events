@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $securityUrl */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $user->getFirstName()]) }},

{{ __('Two-factor authentication is now on for your :appName account. From now on you will be asked for a code from your authenticator app when you sign in.', ['appName' => config('app.name')]) }}

{{ __('Keep your recovery codes somewhere safe. They are the only way back in if you lose access to your authenticator app.') }}

<x-mail::button :url="$securityUrl">
{{ __('Review security settings') }}
</x-mail::button>

{{ __('If you did not make this change, please reset your password immediately and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
