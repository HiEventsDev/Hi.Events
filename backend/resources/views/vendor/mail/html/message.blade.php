<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
@php
            $logoPath = null;
            $organizerName = null;
            $organizerId = null;
            
            // 1. Resolve organizer ID from active variables or fallback request contexts
            if (isset($event) && $event->organizer) {
                $organizerId = $event->organizer->id;
                $organizerName = $event->organizer->name;
            } elseif (isset($order) && $order->event && $order->event->organizer) {
                $organizerId = $order->event->organizer->id;
                $organizerName = $order->event->organizer->name;
            } else {
                $eventId = request()->route('event_id') ?? request()->input('event_id') ?? ($order->event_id ?? null);
                if ($eventId) {
                    $dbEvent = \DB::table('events')->where('id', $eventId)->first();
                    if ($dbEvent) {
                        $organizerId = $dbEvent->organizer_id;
                    }
                }
            }

            // 2. Fetch the true asset path from the custom images ledger if an organizer was resolved
            if ($organizerId) {
                if (!$organizerName) {
                    $dbOrg = \DB::table('organizers')->where('id', $organizerId)->first();
                    $organizerName = $dbOrg->name ?? null;
                }
                
                $dbImage = \DB::table('images')
                    ->where('entity_id', $organizerId)
                    ->where('entity_type', 'HiEvents\DomainObjects\OrganizerDomainObject')
                    ->first();
                    
                if ($dbImage) {
                    $logoPath = $dbImage->path;
                }
            }
        @endphp

        <x-mail::header :url="config('app.frontend_url')">
            @if($logoPath)
                {{-- Render the true dynamic tenant organizer logo asset link --}}
                <img src="{{ config('app.frontend_url') }}/storage/{{ $logoPath }}" class="logo" alt="{{ $organizerName }}" style="max-width: 300px;">
            @elseif($appLogo = config('app.email_logo_url'))
                {{-- Fallback to global setting --}}
                <img src="{{ $appLogo }}" class="logo" alt="{{ config('app.name') }}" style="max-width: 300px;">
            @else
                {{-- Clean fallback typography --}}
		<h2 style="margin:0; font-family: sans-serif; color: #333333;">{{ $organizerName ?? env('VITE_APP_NAME', 'Hi.Events') }}</h2>
            @endif
        </x-mail::header>
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
        <x-mail::footer>
            @if($appEmailFooter = config('app.email_footer_text'))
                {{ $appEmailFooter }}
            @else
                {{-- (c) Hi.Events Ltd 2025 --}}
                {{-- PLEASE NOTE: --}}
                {{-- Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3. --}}
                {{-- You can find the full license text at: https://github.com/HiEventsDev/hi.events/blob/main/LICENSE --}}
                {{-- In accordance with Section 7(b) of the AGPL, we ask that you retain the "Powered by Hi.Events" notice. --}}
                {{-- If you wish to remove this notice, a commercial license is available at: https://hi.events/licensing --}}

                © {{ date('Y') }} {{ config('app.name') }} | Powered by <a title="Manage events and sell tickets online with Hi.Events" href="https://hi.events?utm_source=app-email-footer">Hi.Events</a>
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
