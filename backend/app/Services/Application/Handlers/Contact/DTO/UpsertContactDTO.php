<?php

namespace HiEvents\Services\Application\Handlers\Contact\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Spatie\LaravelData\Optional;

class UpsertContactDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly string|Optional $email = new Optional,
        public readonly string|Optional $first_name = new Optional,
        public readonly string|Optional $last_name = new Optional,
        public readonly array|Optional $attributes = new Optional,
    ) {}
}
