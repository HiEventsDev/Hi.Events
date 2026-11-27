@php /** @see \HiEvents\Enterprise\Crm\Mail\CampaignPendingReviewMail */ @endphp

<x-mail::message>
# {{ __('An audience campaign needs review') }}

<ul style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
<li><strong>{{ __('Campaign') }}:</strong> {{ $campaignName }} (#{{ $campaignId }})</li>
<li><strong>{{ __('Subject') }}:</strong> {{ $campaignSubject }}</li>
<li><strong>{{ __('Account') }}:</strong> {{ $accountName }} (#{{ $accountId }})</li>
<li><strong>{{ __('Recipients') }}:</strong> {{ $recipientsCount }}</li>
<li><strong>{{ __('Reason:') }}</strong> {{ $reason }}</li>
</ul>

@if ($automaticCheckFindings !== [])
{{ __('The automatic check did not approve it:') }}

<ul style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
@foreach ($automaticCheckFindings as $finding)
<li>{{ $finding }}</li>
@endforeach
</ul>

@endif
{{ __('Approve or reject it from the admin area.') }}
</x-mail::message>
