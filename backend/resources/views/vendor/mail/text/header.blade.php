@props(['url'])
@if($url)
[{{ $slot }}]({{ $url }})
@else
{{ $slot }}
@endif
