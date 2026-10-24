@php /** @var string|null $reason */ @endphp
@php /** @var string $supportEmail */ @endphp

<x-mail::message>
{{ __('Hi') }},

{{ __('Your :appName account has been suspended. Your events and organizer pages have been unpublished, and you can no longer sign in to the account.', ['appName' => config('app.name')]) }}

@if ($reason)
{{ __('Reason:') }} {{ $reason }}
@endif

{{ __('If you think this is a mistake, contact us at :email.', ['email' => $supportEmail]) }}

{{ __('Best Regards,') }}<br>
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
