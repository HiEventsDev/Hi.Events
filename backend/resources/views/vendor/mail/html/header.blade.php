@props(['url'])
<tr>
    <td class="header">
@if($url)
        <a href="{{ $url }}" style="display: inline-block;">
            {{ $slot }}
        </a>
@else
        {{ $slot }}
@endif
    </td>
</tr>
