<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.frontend_url')">
            @if($appLogo = config('app.email_logo_url'))
                <img src="{{ $appLogo }}" class="logo" alt="{{ config('app.name') }}"
                     style="max-width: 300px;">
            @else
                <img src="{{ config('app.frontend_url') }}/logo-dark.svg" class="logo" alt="{{ config('app.name') }}"
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
            @if($appEmailFooter = config('app.email_footer'))
                {{ $appEmailFooter }}
            @else
                {{--* PLEASE NOTE:--}}
                {{--* Under the terms of the license, you are not permitted to remove or obscure the powered by footer unless you have a white-label--}}
                {{--* or commercial license.--}}
                {{--* @see https://github.com/HiEventsDev/hi.events/blob/main/LICENCE#L13--}}
                {{--* You can purchase a license at https://hi.events/licensing--}}
                © {{ date('Y') }} <a title="Manage events and sell tickets online with Hi.Events"
                                     href="https://hi.events?utm_source=app-email-footer">Hi.Events</a> - Effortless
                Event Management
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
