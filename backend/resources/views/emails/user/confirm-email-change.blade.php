@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $user->getFirstName()]) }},

{!! __('You have requested to change your email address to <b>:pendingEmail</b>. Please click the link below to confirm this change.', ['pendingEmail' => $user->getPendingEmail()]) !!}

<a href="{{ $link }}">{{ __('Confirm email change') }}</a>

{{ __('If you did not request this change, please immediately change your password.') }}

{{ __('Thanks,') }}
</x-mail::message>
