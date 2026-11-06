<?php

namespace HiEvents\DomainObjects\Enums;

enum OAuthErrorCode: string
{
    use BaseEnum;

    case INVALID_REQUEST = 'invalid_request';
    case INVALID_CLIENT = 'invalid_client';
    case INVALID_GRANT = 'invalid_grant';
    case INVALID_SCOPE = 'invalid_scope';
    case INVALID_TARGET = 'invalid_target';
    case INVALID_REDIRECT_URI = 'invalid_redirect_uri';
    case INVALID_CLIENT_METADATA = 'invalid_client_metadata';
    case UNSUPPORTED_GRANT_TYPE = 'unsupported_grant_type';
    case UNSUPPORTED_RESPONSE_TYPE = 'unsupported_response_type';
    case ACCESS_DENIED = 'access_denied';
}
