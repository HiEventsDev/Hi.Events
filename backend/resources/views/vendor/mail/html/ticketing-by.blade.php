@php($ticketingByLogoUrl = config('app.frontend_url').'/logos/hi-events-horizontal-light.png')
@php($ticketingByLogo = '<a href="'.e(config('app.email_logo_link_url')).'"><img src="'.e($ticketingByLogoUrl).'" class="ticketing-by-logo" height="20" alt="'.e(config('app.name')).'"></a>')
<span class="ticketing-by">{!! str_replace(':platform', $ticketingByLogo, e(__('Ticketing by :platform'))) !!}</span>
