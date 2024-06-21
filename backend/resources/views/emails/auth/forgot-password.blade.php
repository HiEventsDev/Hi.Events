@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
{{ __('Hello') }},

{{ __('You have requested to reset your password for your account on :appName.', ['appName' => config('app.name')]) }}

{{ __('Please click the link below to reset your password.') }}

<a href="{{ $link }}">{{ __('Reset Password') }}</a>

{{ __('If you did not request a password reset, please ignore this email or reply to let us know.') }}

{{ __('Thank you') }}

</x-mail::message>
