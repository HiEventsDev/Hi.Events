@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var \HiEvents\DomainObjects\AccountDomainObject $account */ @endphp
@php /** @var string $approveUrl */ @endphp

<x-mail::message>
# New Account Registration

A new account registration is awaiting your approval.

**Name:** {{ $user->getFirstName() }} {{ $user->getLastName() }}
**Email:** {{ $user->getEmail() }}
**Account:** {{ $account->getName() }}
**Registered:** {{ $account->getCreatedAt() }}

Click the button below to approve this account. Once approved, the user will receive their email confirmation and be able to log in.

<x-mail::button :url="$approveUrl">
    Approve Account
</x-mail::button>

If you do not recognise this registration, simply ignore this email. The account will remain inactive.

{{ __('Best Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
