@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $code */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $user->getFirstName()]) }},

{{ __('Welcome to :appName! We\'re excited to have you aboard!', ['appName' => config('app.name')]) }}

{{ __('Your email confirmation code is:') }}

<h2>{{ $code }}</h2>

{{ __('If you did not create an account with us, no further action is required. Your email address will not be used without confirmation.') }}

{{ __('Best Regards,') }}<br>
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
