<?php

namespace HiEvents\DomainObjects\Enums;

enum OAuthScope: string
{
    use BaseEnum;

    case MCP_READ = 'mcp:read';
    case MCP_WRITE = 'mcp:write';

    public static function accessFor(?string $scope): ApiKeyAccess
    {
        $scopes = array_filter(explode(' ', (string) $scope));

        return $scopes === [] || in_array(self::MCP_WRITE->value, $scopes, true)
            ? ApiKeyAccess::READ_WRITE
            : ApiKeyAccess::READ_ONLY;
    }

    public static function forAccess(ApiKeyAccess $access): string
    {
        return $access === ApiKeyAccess::READ_WRITE
            ? self::MCP_READ->value.' '.self::MCP_WRITE->value
            : self::MCP_READ->value;
    }
}
