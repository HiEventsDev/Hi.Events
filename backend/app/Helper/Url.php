<?php

namespace HiEvents\Helper;

class Url
{
    public const STRIPE_CONNECT_RETURN_URL = 'app.frontend_urls.stripe_connect_return_url';

    public const STRIPE_CONNECT_REFRESH_URL = 'app.frontend_urls.stripe_connect_refresh_url';

    public const RESET_PASSWORD = 'app.frontend_urls.reset_password';

    public const CONFIRM_EMAIL_CHANGE = 'app.frontend_urls.confirm_email_change';

    public const ACCEPT_INVITATION = 'app.frontend_urls.accept_invitation';

    public const CONFIRM_EMAIL_ADDRESS = 'app.frontend_urls.confirm_email_address';

    public const EVENT_HOMEPAGE = 'app.frontend_urls.event_homepage';

    public const ATTENDEE_TICKET = 'app.frontend_urls.attendee_product';

    public const ORDER_SUMMARY = 'app.frontend_urls.order_summary';

    public const ORDER_DETAILS = 'app.frontend_urls.order_details';

    public const ORGANIZER_ORDER_SUMMARY = 'app.frontend_urls.organizer_order_summary';

    public const TICKET_LOOKUP = 'app.frontend_urls.ticket_lookup';

    public const ACCOUNT_DANGER_ZONE = 'app.frontend_urls.account_danger_zone';

    public const PROFILE_SECURITY = 'app.frontend_urls.profile_security';

    public const API_KEYS = 'app.frontend_urls.api_keys';

    public const ACCOUNT_USERS = 'app.frontend_urls.account_users';

    public const ACCOUNT_SETTINGS = 'app.frontend_urls.account_settings';

    public const EVENT_DASHBOARD = 'app.frontend_urls.event_dashboard';

    public const WELCOME = 'app.frontend_urls.welcome';

    public const LIFECYCLE_EMAIL_UNSUBSCRIBE = 'app.frontend_urls.lifecycle_email_unsubscribe';

    public const SSO_CALLBACK = 'app.frontend_urls.sso_callback';

    public static function getFrontEndUrlFromConfig(string $key, array $queryParams = []): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/').config($key);

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    public static function getApiUrl(string $path, array $queryParams = []): string
    {
        $url = rtrim(config('app.api_url'), '/').'/'.ltrim($path, '/');

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    /**
     * @param  string  $path  The relative path to the asset.
     * @return string The fully qualified URL to the asset, either via CDN or storage disk.
     */
    public static function getCdnUrl(string $path): string
    {
        $envCDNUrl = env('APP_CDN_URL');

        if ($envCDNUrl) {
            return $envCDNUrl.'/'.$path;
        }

        $disk = config('filesystems.public', 'public');

        return app('filesystem')->disk($disk)->url($path);
    }

    private static function addQueryParamsToUrl(array $queryParams, mixed $url): mixed
    {
        if (! empty($queryParams)) {
            $query = http_build_query($queryParams);
            $url = rtrim($url, '/').'?'.$query;
        }

        return $url;
    }
}
