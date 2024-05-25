@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
Hello,

You have requested to reset your password for your account on {{ config('app.name') }}.

Please click the link below to reset your password.

<a href="{{ $link }}">Reset Password</a>

If you did not request a password reset, please ignore this email or reply to let us know.

Thank you

</x-mail::message>
