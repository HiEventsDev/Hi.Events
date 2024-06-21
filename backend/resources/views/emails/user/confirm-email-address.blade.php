@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $user->getFirstName()]) }},

{{ __('Welcome to :appName! We\'re excited to have you aboard!', ['appName' => config('app.name')]) }}

{{ __('To get started and activate your account, please click the link below to confirm your email address:') }}

<x-mail::button :url="$link">
    {{ __('Confirm Your Email') }}
</x-mail::button>

{{ __('If you did not create an account with us, no further action is required. Your email address will not be used without confirmation.') }}

{{ __('Best Regards,') }}<br>
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
