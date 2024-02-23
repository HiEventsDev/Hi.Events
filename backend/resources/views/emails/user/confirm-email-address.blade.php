@php /** @var \TicketKitten\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
Hi {{ $user->getFirstName() }},

{{ __('Welcome to :appName! We\'re excited to have you aboard!.', ['appName' => config('app.name')]) }}

{{ __('To get started and activate your account, please click the link below to confirm your email address:') }}

<x-mail::button :url="$link">
{{ __('Confirm Your Email') }}
</x-mail::button>

{{ __('If you did not create an account with us, no further action is required. Your email address will not be used without confirmation.') }}

{{ __('Should you have any questions or require assistance, feel free to reach out to our support team.') }}

{{ __('Best Regards,') }}
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
