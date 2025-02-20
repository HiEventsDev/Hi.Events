<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.email_logo_link_url')">
            @if($appLogo = config('app.email_logo_url'))
                <img src="{{ $appLogo }}" class="logo" alt="{{ config('app.name') }}"
                     style="max-width: 300px;">
            @else
                <img src="{{ config('app.frontend_url') }}/logo-dark.png" class="logo" alt="{{ config('app.name') }}"
                     style="max-width: 300px;">
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
