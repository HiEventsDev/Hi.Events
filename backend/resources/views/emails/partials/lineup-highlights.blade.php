@php /** @var \HiEvents\Services\Domain\Lineup\DTO\EmailLineupHighlightsDTO|null $lineup */ @endphp
@if($lineup)
<div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin: 16px 0; line-height: 1.6;">
<strong>{{ $lineup->heading }}</strong><br>
@if($lineup->names !== [])
{{ implode(', ', $lineup->names) }}<br>
@endif
@foreach($lineup->slots as $slot)
<span style="color: #6b7280;">{{ $slot['time'] }}</span> · {{ $slot['title'] }}@if($slot['stage']) · {{ $slot['stage'] }}@endif<br>
@endforeach
<a href="{{ $lineup->url }}">{{ $lineup->slots === [] ? __('See the full lineup') : __('See the full schedule') }}</a>
</div>
@endif
