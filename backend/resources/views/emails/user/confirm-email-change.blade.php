@php /** @var \HiEvents\DomainObjects\UserDomainObject $user */ @endphp
@php /** @var string $link */ @endphp

<x-mail::message>
Hi {{ $user->getFirstName() }},

You have requested to change your email address to <b>{{ $user->getPendingEmail() }}</b>. Please click the link
below to confirm this change.

<a href="{{ $link }}">Confirm email change</a>

If you did not request this change, please immediately change your password.

Thanks,
</x-mail::message>
