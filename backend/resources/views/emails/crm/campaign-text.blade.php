@php /** @var string $plainTextBody */ @endphp
@php /** @var string $organizerName */ @endphp
@php /** @var \HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\CampaignEventCardDTO|null $eventCard */ @endphp
@php /** @var string|null $unsubscribeUrl */ @endphp
@php /** @var bool $isTest */ @endphp
@php /** @see \HiEvents\Enterprise\Crm\Mail\CampaignMail */ @endphp
@if(config('app.saas_mode_enabled') && ! ($mailBranding ?? null)?->hideHiEventsBranding)
{!! __('This message is from :organizer, not from :platform.', ['organizer' => $organizerName, 'platform' => config('app.name')]) !!}

@endif
{!! $plainTextBody !!}
@if($eventCard)

{!! $eventCard->title !!}
@if($eventCard->date_line)
{!! $eventCard->date_line !!}
@endif
@if($eventCard->location_line)
{!! $eventCard->location_line !!}
@endif
{!! $eventCard->button_label !!}: {!! $eventCard->url !!}
@endif

--
{!! __('You are receiving this email because you opted in to hear from :organizer.', ['organizer' => $organizerName]) !!}
@if($unsubscribeUrl)
{!! __('Unsubscribe') !!}: {!! $unsubscribeUrl !!}
@elseif($isTest)
{!! __('Recipients will see an unsubscribe link here.') !!}
@endif
{!! __('If you believe this is spam, please report it to :email.', ['email' => config('mail.from.address')]) !!}

© {{ date('Y') }} {!! config('app.name') !!}. {!! __('All rights reserved.') !!}
