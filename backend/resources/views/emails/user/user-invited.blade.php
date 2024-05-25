@php /** @var \HiEvents\DomainObjects\UserDomainObject $invitedUser */ @endphp
@php /** @var string $inviteLink */ @endphp
@php /** @var string $appName */ @endphp

<x-mail::message>
Hi {{ $invitedUser->getFirstName() }},

You've been invited to join {{ $appName }}.

To accept the invitation, please click the link below:

<a href="{{ $inviteLink }}">Accept Invitation</a>

Thank you,<br>
{{ $appName }}
</x-mail::message>
