@php /** @var string|null $preheader */ @endphp
@php /** @var string $body */ @endphp
@php /** @var string $organizerName */ @endphp
@php /** @var string|null $postalAddress */ @endphp
@php /** @var \HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignEventCardDTO|null $eventCard */ @endphp
@php /** @var string|null $unsubscribeUrl */ @endphp
@php /** @var bool $isTest */ @endphp

@php /** @see \HiEvents\Enterprise\Crm\Mail\CampaignMail */ @endphp

<x-mail::message>
@if($preheader)
<div style="display: none; max-height: 0; max-width: 0; overflow: hidden; opacity: 0; mso-hide: all;">{{ $preheader }}</div>
@endif
@if(config('app.saas_mode_enabled') && ! ($mailBranding ?? null)?->hideHiEventsBranding)
<div style="background-color: #f7f5fb; border: 1px solid #ebe7f2; border-radius: 10px; padding: 10px 16px; margin: 0 0 24px 0; color: #56506a; font-size: 13px; line-height: 1.6; text-align: center; overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
{{ __('This message is from :organizer, not from :platform.', ['organizer' => $organizerName, 'platform' => config('app.name')]) }}
</div>
@endif

<div style="overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">{!! $body !!}</div>

@if($eventCard)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0 8px; border: 1px solid #ebe7f2; border-radius: 12px; border-collapse: separate;">
@if($eventCard->image_url)
<tr><td style="padding: 0;"><img src="{{ $eventCard->image_url }}" alt="{{ $eventCard->title }}" width="560" style="display: block; width: 100%; height: auto; max-height: 280px; object-fit: cover; border-radius: 12px 12px 0 0;"></td></tr>
@endif
<tr><td style="padding: 20px 22px; overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
<p style="margin: 0 0 6px; font-size: 18px; line-height: 1.35; font-weight: 700; color: #1d1b26;">{{ $eventCard->title }}</p>
@if($eventCard->date_line)
<p style="margin: 0; font-size: 14px; line-height: 1.5; color: #56506a;">{{ $eventCard->date_line }}</p>
@endif
@if($eventCard->location_line)
<p style="margin: 0; font-size: 14px; line-height: 1.5; color: #56506a;">{{ $eventCard->location_line }}</p>
@endif
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top: 16px;"><tr><td style="border-radius: 8px; background-color: {{ \HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignButtonStyler::COLOR }};"><a href="{{ $eventCard->url }}" style="display: inline-block; padding: 12px 22px; color: #ffffff; font-weight: 600; font-size: 15px; text-decoration: none; border-radius: 8px;">{{ $eventCard->button_label }}</a></td></tr></table>
</td></tr>
</table>
@endif

<div style="color: #6b6b6b; margin-top: 30px; font-size: .8em; overflow-wrap: anywhere; word-wrap: break-word; word-break: break-word;">
{{ __('You are receiving this email because you opted in to hear from :organizer.', ['organizer' => $organizerName]) }}
@if($postalAddress)
<br>{{ $postalAddress }}<br>
@endif
@if($unsubscribeUrl)
<a href="{{ $unsubscribeUrl }}">{{ __('Unsubscribe') }}</a>.
@elseif($isTest)
{{ __('Recipients will see an unsubscribe link here.') }}
@endif
{!! str_replace(':email', '<a href="mailto:'.e(config('mail.from.address')).'">'.e(config('mail.from.address')).'</a>', e(__('If you believe this is spam, please report it to :email.'))) !!}
</div>
</x-mail::message>
