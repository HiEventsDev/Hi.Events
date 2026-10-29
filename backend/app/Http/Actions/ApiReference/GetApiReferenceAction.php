<?php

namespace HiEvents\Http\Actions\ApiReference;

use HiEvents\Http\Actions\BaseAction;
use HiEvents\OpenApi\IntegrationApiReference;
use Illuminate\Http\JsonResponse;

class GetApiReferenceAction extends BaseAction
{
    public function __construct(
        private readonly IntegrationApiReference $reference,
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->jsonResponse($this->reference->published());
    }
}
