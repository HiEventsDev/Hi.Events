@php /** @var string $accountName */ @endphp
@php /** @var string $scheduledDeletionDate */ @endphp
@php /** @var string $cancelLink */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $accountName]) }},

{{ __('This is a reminder that your :appName account is scheduled for permanent deletion on :date.', ['appName' => config('app.name'), 'date' => $scheduledDeletionDate]) }}

{{ __('If you want to keep your account, cancel the deletion before this date:') }}

<x-mail::button :url="$cancelLink">
    {{ __('Cancel Account Deletion') }}
</x-mail::button>

{{ __('If you take no action, the deletion will proceed automatically and cannot be undone.') }}

{{ __('Best Regards,') }}<br>
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
