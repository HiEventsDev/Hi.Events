{{-- Custom Liquid Template Wrapper --}}
<x-mail::message>
{!! $renderedBody !!}

@if(isset($renderedCta))
<x-mail::button :url="$renderedCta['url']">
    {{ $renderedCta['label'] }}
</x-mail::button>
@endif

{!! $eventSettings->getGetEmailFooterHtml() !!}

</x-mail::message>
