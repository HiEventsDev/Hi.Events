@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp
@php /** @var string|null $signInProviders */ @endphp

<x-mail::message>
{{ __('Hello') }},

{{ __('You have requested to reset your password for your account on :appName.', ['appName' => config('app.name')]) }}

@if($signInProviders)
{{ __('You usually sign in with :providers. You can keep using it, or set a password with the link below.', ['providers' => $signInProviders]) }}
@endif

{{ __('Please click the link below to reset your password.') }}

<a href="{{ $link }}">{{ __('Reset Password') }}</a>

{{ __('If you did not request a password reset, please ignore this email or reply to let us know.') }}

{{ __('Thank you') }}

</x-mail::message>
