@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var int $recoveryCodesRemaining */ @endphp
@php /** @var string $securityUrl */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $user->getFirstName()]) }},

{{ __('A recovery code was just used to sign in to your :appName account.', ['appName' => config('app.name')]) }}

@if($recoveryCodesRemaining === 0)
{{ __('You have no recovery codes left. Generate new ones now so you can still get in if you lose your authenticator app.') }}
@else
{{ __('Recovery codes remaining: :count', ['count' => $recoveryCodesRemaining]) }}
@endif

<x-mail::button :url="$securityUrl">
{{ __('Manage recovery codes') }}
</x-mail::button>

{{ __('If this was not you, reset your password immediately and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
