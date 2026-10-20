@php /** @var string $actorEmail */ @endphp
@php /** @var string $change */ @endphp
@php /** @var string $settingsUrl */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __(':actorEmail made a change to single sign-on for your :appName account:', ['actorEmail' => $actorEmail, 'appName' => config('app.name')]) }}

**{{ $change }}**

<x-mail::button :url="$settingsUrl">
{{ __('Review single sign-on settings') }}
</x-mail::button>

{{ __('If you did not expect this, review your team\'s admins and contact support.') }}

{{ __('Thanks,') }}
</x-mail::message>
