@php /** @var string $accountName */ @endphp
@php /** @var bool $wasAnonymized */ @endphp

<x-mail::message>
{{ __('Hi :name', ['name' => $accountName]) }},

{{ __('Your :appName account has been permanently deleted.', ['appName' => config('app.name')]) }}

@if ($wasAnonymized)
{{ __('All personal information has been removed. Anonymized transaction records (amounts, dates, and invoice details) have been retained as required for legal and tax purposes.') }}
@else
{{ __('All of your account data has been permanently removed.') }}
@endif

{{ __('Thank you for using :appName. You are welcome back at any time.', ['appName' => config('app.name')]) }}

{{ __('Best Regards,') }}<br>
{{ __('The :appName Team', ['appName' => config('app.name')]) }}
</x-mail::message>
