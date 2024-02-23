<?php

namespace TicketKitten\Helper;

class Url
{
    public const STRIPE_CONNECT_RETURN_URL = 'app.frontend_urls.stripe_connect_return_url';
    public const STRIPE_CONNECT_REFRESH_URL = 'app.frontend_urls.stripe_connect_refresh_url';
    public const RESET_PASSWORD = 'app.frontend_urls.reset_password';
    public const CONFIRM_EMAIL_CHANGE = 'app.frontend_urls.confirm_email_change';
    public const ACCEPT_INVITATION = 'app.frontend_urls.accept_invitation';
    public const CONFIRM_EMAIL_ADDRESS = 'app.frontend_urls.confirm_email_address';

    public static function getFrontEndUrlFromConfig(string $key, array $queryParams = []): string
    {
        $url = config('app.frontend_url') . config($key);

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    public static function getFrontendUrl(string $path = '', array $queryParams = []): string
    {
        $url = config('app.frontend_url') . $path;

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    public static function getCdnUrl(string $path): string
    {
        return config('app.cnd_url') . '/' . $path;
    }

    private static function addQueryParamsToUrl(array $queryParams, mixed $url): mixed
    {
        if (!empty($queryParams)) {
            $query = http_build_query($queryParams);
            $url = rtrim($url, '/') . '?' . $query;
        }

        return $url;
    }
}
