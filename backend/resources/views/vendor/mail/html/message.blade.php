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

        <x-mail::header :url="config('app.email_logo_link_url', config('app.frontend_url'))">
            @if($logoPath)
                {{-- Render the true dynamic tenant organizer logo asset link --}}
                <img src="{{ config('app.frontend_url') }}/storage/{{ $logoPath }}" class="logo" alt="{{ $organizerName }}" style="max-width: 300px;">
            @elseif($appLogo = config('app.email_logo_url'))
                {{-- Fallback to global setting --}}
                <img src="{{ $appLogo }}" class="logo" alt="{{ config('app.name') }}" style="max-width: 300px;">
            @else
                <img src="{{ config('app.frontend_url') }}/logos/hi-events-stacked-light.png" class="logo" alt="{{ config('app.name') }}">
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
            @elseif(app(\HiEvents\Enterprise\Licensing\LicenceService::class)->allows(\HiEvents\Enterprise\Licensing\LicensedFeature::WHITE_LABEL))
                © {{ date('Y') }} {{ config('app.name') }}
            @else
                {{-- (c) Hi.Events Ltd 2024-present --}}
                {{-- Hi.Events is licensed under the GNU Affero General Public License (AGPL) version 3. --}}
                {{-- The full licence text is in the LICENCE file in the repository root. --}}
                {{-- Under Section 7(b) of the AGPL, the "Powered by Hi.Events" notice must stay on all web pages --}}
                {{-- and emails. If you modify Hi.Events you may rephrase it, for example "Powered by [Your Company] --}}
                {{-- based on Hi.Events", but it must still link to https://hi.events. --}}
                {{-- The notice must stay clearly visible and legible. Do not hide or obscure it, for example by --}}
                {{-- shrinking its font size, lowering its contrast, matching its colour to the background, covering --}}
                {{-- it or moving it off-screen. --}}
                {{-- To remove the notice you need a commercial licence: https://hi.events/licensing --}}
                {{-- With a licence, hide it through your licence key or configuration rather than by editing this code. --}}
                {{-- Commercial licences help keep Hi.Events free and open source. To keep that fair for everyone who --}}
                {{-- pays, we may work with a third-party compliance partner to find installations that remove or --}}
                {{-- obscure this notice without a licence. If you hear from us or them, it will start as a friendly --}}
                {{-- conversation, and you'll have 30 days to get a licence or restore the notice. --}}

                © {{ date('Y') }} {{ config('app.name') }} | Powered by <a title="Manage events and sell tickets online with Hi.Events" href="https://hi.events?utm_source=app-email-footer">Hi.Events</a>
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
