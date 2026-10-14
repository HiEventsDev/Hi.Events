@php /** @var string $renderedBody */ @endphp
@php /** @var string $ctaLabel */ @endphp
@php /** @var string $ctaUrl */ @endphp
@php /** @var string $signatureName */ @endphp
@php /** @var string|null $signatureTitle */ @endphp
@php /** @var string $unsubscribeUrl */ @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
</head>
<body style="margin: 0; padding: 0; background-color: #ffffff;">
<div style="max-width: 600px; padding: 24px 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #1f1f1f; text-align: left;">
{!! $renderedBody !!}
<p><a href="{{ $ctaUrl }}" style="color: #1a5fb4;">{{ $ctaLabel }}</a></p>
<p>{{ $signatureName }}@if($signatureTitle)<br><span style="color: #6b6b6b;">{{ $signatureTitle }}</span>@endif</p>
<p style="margin-top: 32px; font-size: 12px; color: #8a8a8a;">{{ __('You are receiving this because you have a :app_name organizer account.', ['app_name' => config('app.name')]) }} <a href="{{ $unsubscribeUrl }}" style="color: #8a8a8a;">{{ __('Unsubscribe') }}</a></p>
</div>
</body>
</html>
