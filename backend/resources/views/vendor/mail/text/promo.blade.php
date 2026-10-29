@props(['url'])
{{ __('Planning an event of your own?') }} {{ __('Sell tickets and manage attendees with :app_name. It\'s free to get started.', ['app_name' => config('app.name')]) }}
{{ __('Create your event') }}: {{ $url }}
