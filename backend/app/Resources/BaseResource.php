<?php

namespace HiEvents\Resources;

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
}
