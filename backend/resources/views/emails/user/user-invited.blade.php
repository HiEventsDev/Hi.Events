@php /** @var \HiEvents\DomainObjects\UserDomainObject $invitedUser */ @endphp
@php /** @var string $inviteLink */ @endphp
@php /** @var string $appName */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $invitedUser->getFirstName()]) }},

{{ __('You\'ve been invited to join :appName.', ['appName' => $appName]) }}

{{ __('To accept the invitation, please click the link below:') }}

<a href="{{ $inviteLink }}">{{ __('Accept Invitation') }}</a>

{{ __('Thank you') }},<br>
{{ $appName }}
</x-mail::message>
