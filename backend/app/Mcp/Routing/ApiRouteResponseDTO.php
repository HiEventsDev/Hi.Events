<?php

namespace HiEvents\Mcp\Routing;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Http\ResponseCodes;

class ApiRouteResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status >= ResponseCodes::HTTP_OK && $this->status < ResponseCodes::HTTP_MULTIPLE_CHOICES;
    }

    public function data(): array
    {
        return $this->body['data'] ?? [];
    }
}
