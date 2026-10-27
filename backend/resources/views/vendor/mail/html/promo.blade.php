@props(['url'])
<tr>
    <td>
        <table class="promo" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td class="promo-cell" align="center">
                    <p class="promo-heading">{{ __('Planning an event of your own?') }}</p>
                    <p class="promo-text">{{ __('Sell tickets and manage attendees with :app_name. It\'s free to get started.', ['app_name' => config('app.name')]) }}</p>
                    <a href="{{ $url }}" class="promo-link">{{ __('Create your event') }} &rarr;</a>
                </td>
            </tr>
        </table>
    </td>
</tr>
