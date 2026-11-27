<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Automation\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use JsonException;

class AutomationFailureDTO extends BaseDataObject
{
    public const EVENT_AWAITING_REVIEW = 'EVENT_AWAITING_REVIEW';

    public const REVIEW_EXPIRED = 'REVIEW_EXPIRED';

    /**
     * @param  array<string, int|string>  $params
     */
    public function __construct(
        public readonly string $code,
        public readonly array $params = [],
    ) {}

    public static function decode(?string $stored): ?self
    {
        if ($stored === null) {
            return null;
        }

        try {
            $decoded = json_decode($stored, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) && is_string($decoded['code'] ?? null)
            ? new self($decoded['code'], (array) ($decoded['params'] ?? []))
            : null;
    }

    public function forStorage(): string
    {
        return (string) json_encode(['code' => $this->code, 'params' => $this->params]);
    }
}
