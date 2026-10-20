@php /** @var string $plainTextBody */ @endphp
@php /** @var string $ctaLabel */ @endphp
@php /** @var string $ctaUrl */ @endphp
@php /** @var string $signatureName */ @endphp
@php /** @var string|null $signatureTitle */ @endphp
@php /** @var string $unsubscribeUrl */ @endphp
{!! $plainTextBody !!}

{!! $ctaLabel !!}: {!! $ctaUrl !!}

{!! $signatureName !!}
@if($signatureTitle)
{!! $signatureTitle !!}
@endif

--
{!! __('You are receiving this because you have a :app_name organizer account.', ['app_name' => config('app.name')]) !!}
{!! __('Unsubscribe') !!}: {!! $unsubscribeUrl !!}
