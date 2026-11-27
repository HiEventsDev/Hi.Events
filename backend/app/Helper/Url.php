<?php

namespace HiEvents\Helper;

class Url
{
    private const CDN_PROBE_PATH = '__cdn_probe__';

    public const STRIPE_CONNECT_RETURN_URL = 'app.frontend_urls.stripe_connect_return_url';

    public const STRIPE_CONNECT_REFRESH_URL = 'app.frontend_urls.stripe_connect_refresh_url';

    public const RESET_PASSWORD = 'app.frontend_urls.reset_password';

    public const CONFIRM_EMAIL_CHANGE = 'app.frontend_urls.confirm_email_change';

    public const ACCEPT_INVITATION = 'app.frontend_urls.accept_invitation';

    public const CONFIRM_EMAIL_ADDRESS = 'app.frontend_urls.confirm_email_address';

    public const EVENT_HOMEPAGE = 'app.frontend_urls.event_homepage';

    public const EVENT_PREVIEW = 'app.frontend_urls.event_preview';

    public const ATTENDEE_TICKET = 'app.frontend_urls.attendee_product';

    public const ORDER_SUMMARY = 'app.frontend_urls.order_summary';

    public const ORDER_DETAILS = 'app.frontend_urls.order_details';

    public const ORDER_PAYMENT = 'app.frontend_urls.order_payment';

    public const ORGANIZER_ORDER_SUMMARY = 'app.frontend_urls.organizer_order_summary';

    public const TICKET_LOOKUP = 'app.frontend_urls.ticket_lookup';

    public const TICKET_TRANSFER_CLAIM = 'app.frontend_urls.ticket_transfer_claim';

    public const EVENT_RESALE = 'app.frontend_urls.event_resale';

    public const ACCOUNT_DANGER_ZONE = 'app.frontend_urls.account_danger_zone';

    public const PROFILE_SECURITY = 'app.frontend_urls.profile_security';

    public const API_KEYS = 'app.frontend_urls.api_keys';

    public const ACCOUNT_USERS = 'app.frontend_urls.account_users';

    public const ACCOUNT_SETTINGS = 'app.frontend_urls.account_settings';

    public const ACCOUNT_BILLING = 'app.frontend_urls.account_billing';

    public const REGISTER = 'app.frontend_urls.register';

    public const EVENT_DASHBOARD = 'app.frontend_urls.event_dashboard';

    public const ORGANIZER_EVENTS = 'app.frontend_urls.organizer_events';

    public const WELCOME = 'app.frontend_urls.welcome';

    public const LIFECYCLE_EMAIL_UNSUBSCRIBE = 'app.frontend_urls.lifecycle_email_unsubscribe';

    public const CRM_UNSUBSCRIBE = 'app.frontend_urls.crm_unsubscribe';

    public const CRM_SMS_UNSUBSCRIBE = 'app.frontend_urls.crm_sms_unsubscribe';

    public const CRM_CONFIRM_SUBSCRIPTION = 'app.frontend_urls.crm_confirm_subscription';

    public const CRM_CAMPAIGN = 'app.frontend_urls.crm_campaign';

    public const CRM_AUTOMATIONS = 'app.frontend_urls.crm_automations';

    public const SSO_CALLBACK = 'app.frontend_urls.sso_callback';

    public static function getFrontEndUrlFromConfig(string $key, array $queryParams = []): string
    {
        $url = rtrim((string) config('app.frontend_url'), '/').config($key);

        return self::addQueryParamsToUrl($queryParams, $url);
    }

    /**
     * @param  string  $path  The relative path to the asset.
     * @return string The fully qualified URL to the asset, either via CDN or storage disk.
     */
    public static function getCdnUrl(string $path): string
    {
        $cdnUrl = config('app.cdn_url');

        if ($cdnUrl) {
            return $cdnUrl.'/'.$path;
        }

        $disk = config('filesystems.public', 'public');

        return app('filesystem')->disk($disk)->url($path);
    }

    public static function getCdnBaseUrl(): string
    {
        $probe = self::getCdnUrl(self::CDN_PROBE_PATH);

        return substr($probe, 0, -strlen(self::CDN_PROBE_PATH));
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
