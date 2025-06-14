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
    public const ORGANIZER_ORDER_SUMMARY = 'app.frontend_urls.organizer_order_summary';

    public static function getFrontEndUrlFromConfig(string $key, array $queryParams = []): string
    {
        $url = config('app.frontend_url') . config($key);

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    public static function getApiUrl(string $path, array $queryParams = []): string
    {
        $url = rtrim(config('app.api_url'), '/') . '/' . ltrim($path, '/');

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    /**
    * Generates a CDN URL for the given path if a CDN URL is configured.
    * Falls back to generating a URL using the specified or default filesystem disk.
    *
    * @param string $path The relative path to the asset.
    * @return string The fully qualified URL to the asset, either via CDN or storage disk.
    */
    public static function getCdnUrl(string $path): string
    {
        // Fetch the CDN URL from environment variables
        // Checking against the env variable instead of config() as config falls back to the default value
        // and we want to ensure that if the env variable is not set, we do not use a default value.
        $envCDNUrl = env('APP_CDN_URL'); 

        if ($envCDNUrl) {
            return  $envCDNUrl . '/' . $path;
         }

        $disk = config('filesystems.public', 'public');
        return app('filesystem')->disk($disk)->url($path);
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
