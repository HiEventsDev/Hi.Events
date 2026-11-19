@php /** @var string $heading */ @endphp
@php /** @var string $summary */ @endphp
@php /** @var string $reason */ @endphp
@php /** @var string $campaignUrl */ @endphp

@php /** @see \HiEvents\Enterprise\Crm\Mail\CampaignNotSentMail */ @endphp

<x-mail::message>
# {{ $heading }}

<div style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">{{ $summary }}</div>

<div style="margin: 16px 0; padding: 12px 16px; border-left: 3px solid #ebe7f2; background-color: #f7f5fb; overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;"><strong>{{ __('Reason:') }}</strong> {{ $reason }}</div>

<x-mail::button :url="$campaignUrl">
{{ __('Open campaign') }}
</x-mail::button>
</x-mail::message>
