<?php

namespace HiEvents\Resources;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Services\Domain\Auth\Access\AccountUserAccessService;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class BaseResource extends JsonResource
{
    protected static array $additionalData = [];

    public static function collectionWithAdditionalData($resource, $data): JsonResource
    {
        static::$additionalData = $data;

        return static::collection($resource);
    }

    public function getAdditionalDataByKey(string $key)
    {
        return static::$additionalData[$key] ?? null;
    }

    protected function isPermitted(Permission $permission): bool
    {
        return ! app(AccountUserAccessService::class)->isWithheldFromCurrentUser($permission);
    }
}
