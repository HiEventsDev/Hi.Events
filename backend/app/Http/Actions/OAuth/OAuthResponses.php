<?php

namespace HiEvents\Http\Actions\OAuth;

use HiEvents\Exceptions\OAuthException;
use Illuminate\Http\JsonResponse;

trait OAuthResponses
{
    protected function oauthErrorResponse(OAuthException $exception): JsonResponse
    {
        return $this->noStore($this->jsonResponse([
            'error' => $exception->errorCode->value,
            'error_description' => $exception->getMessage(),
        ], $exception->status));
    }

    protected function noStore(JsonResponse $response): JsonResponse
    {
        return $response->withHeaders(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
}
