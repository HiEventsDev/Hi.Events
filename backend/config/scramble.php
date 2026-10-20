<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use HiEvents\OpenApi\BaseActionResponseTypeExtension;
use HiEvents\OpenApi\BinaryResponseTypeToSchema;
use HiEvents\OpenApi\ErrorResponsesExtension;
use HiEvents\OpenApi\PaginationQueryParametersExtension;
use HiEvents\OpenApi\UnwrappedResourceResponseTypeToSchema;

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => '',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'openapi.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION') ?? trim(@file_get_contents(base_path('VERSION')) ?: '0.0.1'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => <<<'MARKDOWN'
The Hi.Events API powers event management, ticketing, and checkout.

## Authentication

Management endpoints require a bearer token in the `Authorization` header. Endpoints under `/public/*` — event pages, checkout, ticket lookup, check-in — require no authentication and are marked *(public)*.

### API keys

For integrations, create an API key under **Settings → API keys** and send it with every request:

```bash
curl https://your-hi-events-host/api/events \
  -H "Authorization: Bearer hie_…" \
  -H "Accept: application/json"
```

- A key acts as the person who created it, in the one account it was created in, with that person's role, permissions and event access. It never has more access than its owner, and changes to the owner's role apply straight away.
- **Read and write** keys can call every endpoint the owner can. **Read-only** keys can call `GET` endpoints and exports; other requests return `403` with `error_code: API_KEY_READ_ONLY`.
- Keys can't sign in, or manage the user's profile, sign-in security, team, API keys, payouts or account settings. Those endpoints return `403` with `error_code: API_KEY_NOT_ALLOWED`.
- An expired, revoked or unknown key returns `401`. A key is revoked when its owner revokes it, when an admin revokes it, when the owner is deactivated or leaves the account, and when the owner's password is reset or their single sign-on changes.
- The full key is shown once, when it's created. Hi.Events stores only a hash of it.

### Session tokens

The Hi.Events web app uses a JWT obtained from `POST /auth/login`. It works on every management endpoint, including the ones API keys can't use.

### Two-factor authentication

When the user has two-factor authentication turned on, `POST /auth/login` returns `200` with `token: null`, `two_factor_required: true` and a `two_factor_challenge_token` that is valid for 10 minutes. Complete the login with `POST /auth/login/two-factor`, sending the challenge token plus either a 6-digit `code` from the user's authenticator app or a `recovery_code`. A wrong code returns `422`; an expired challenge, or too many wrong codes, returns `401` with `error_code: TWO_FACTOR_CHALLENGE_EXPIRED` and the login must start again. Users with several accounts verify once, then call `POST /auth/login/two-factor` again with the same challenge token and an `account_id`.

Tokens work the same once issued: two-factor authentication is only checked at login.

If an account requires two-factor authentication and the user has not set it up, authenticated endpoints return `403` with `error_code: TWO_FACTOR_SETUP_REQUIRED` until they do. `GET /users/me`, `GET /accounts`, the `/auth/*` endpoints and the `/users/me/two-factor/*` setup endpoints stay available.

## Errors

Errors return JSON with a `message`. Validation errors (`422`) also include an `errors` object keyed by field. Access errors include an `error_code`:

| Status | `error_code` | Meaning |
| --- | --- | --- |
| `401` | — | Missing, invalid or expired token, or an invalid, expired or revoked API key |
| `401` | `SESSION_REVOKED` | The session token was signed out everywhere, for example after a password reset |
| `401` | `SSO_REQUIRED` | The user's organization requires single sign-on; sign in again through it |
| `401` | `SSO_SESSION_EXPIRED` | A session started through single sign-on can't be refreshed; sign in again |
| `403` | `API_KEY_READ_ONLY` | A read-only API key tried to make a change |
| `403` | `API_KEY_NOT_ALLOWED` | The endpoint can't be used with an API key |
| `403` | `READ_ONLY_ROLE` | The user has view-only access to the account |
| `403` | `PERMISSION_REQUIRED` | The user's role lacks a permission the endpoint needs (for example refunds or financial data) |
| `403` | `ACCESS_RESTRICTED` | The user only has access to some organizers or events, and this isn't one of them |
| `403` | `TWO_FACTOR_SETUP_REQUIRED` | The account requires two-factor authentication and the user hasn't set it up |
| `403` | `ACCOUNT_PENDING_DELETION` | The account is scheduled for deletion |
| `403` | `FEATURE_UNAVAILABLE` | The feature isn't available on this installation or plan |
| `429` | — | Too many requests; wait for the number of seconds in the `Retry-After` header |

## Rate limits

Management endpoints are rate limited per user, shared between the web app and all of that user's API keys. The default is 180 requests a minute; self-hosted installations can change it with `APP_API_RATE_LIMIT_PER_MINUTE`.

## Stability

The API isn't versioned yet. Endpoints that accept API keys are the integration surface, and we keep them backward compatible: new endpoints, optional request fields and response fields can be added at any time, so ignore fields you don't recognise. Removing or renaming an endpoint or field, or changing its type or meaning, is a breaking change; it's avoided where possible and always called out in the release notes. Endpoints marked *Not available to API keys* serve the Hi.Events web app and may change without notice.

## Outgoing webhooks

Webhooks are configured per event or organizer (see the *Webhooks* endpoints) and deliver a `POST` request to your URL for each subscribed event:

```json
{
    "event_type": "order.created",
    "event_sent_at": "2026-08-07T10:00:00+00:00",
    "payload": { }
}
```

`payload` matches the schema of the corresponding resource (see the *Schemas* section):

| Events | Payload schema |
| --- | --- |
| `order.created`, `order.updated`, `order.marked_as_paid`, `order.refunded`, `order.cancelled` | `OrderResource` |
| `attendee.created`, `attendee.updated`, `attendee.cancelled` | `AttendeeResource` |
| `product.created`, `product.updated`, `product.deleted` | `ProductResource` |
| `event.created`, `event.updated`, `event.archived` | `EventResource` |
| `checkin.created`, `checkin.deleted` | `AttendeeCheckInResource` |
| `occurrence.cancelled` | `EventOccurrenceResource` |

`email` on an order payload is `null` for box office sales made without a buyer email. `order.created` and `order.cancelled` additionally emit an `attendee.created` / `attendee.cancelled` webhook for each attendee on the order. Deliveries are signed: the `Signature` header contains an HMAC-SHA256 hash of the JSON body using your webhook's secret.
MARKDOWN,
    ],

    'ui' => [
        'title' => null,
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => [
        'Default' => '/',
    ],

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [
        BaseActionResponseTypeExtension::class,
        BinaryResponseTypeToSchema::class,
        ErrorResponsesExtension::class,
        PaginationQueryParametersExtension::class,
        UnwrappedResourceResponseTypeToSchema::class,
    ],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
