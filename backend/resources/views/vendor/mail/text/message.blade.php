@php($mailBranding ??= null)
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
@if($mailBranding?->hideHiEventsBranding)
        <x-mail::header :url="$mailBranding->organizerUrl">
            {{ $mailBranding->organizerName }}
        </x-mail::header>
@else
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
@endif
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
@if($mailBranding?->showHiEventsPromo)
        <x-mail::promo :url="$mailBranding->promoUrl" />
@endif
        <x-mail::footer>
            © {{ date('Y') }} {{ $mailBranding?->hideHiEventsBranding ? $mailBranding->organizerName : config('app.name') }}. @lang('All rights reserved.')
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
