<?php

namespace HiEvents\Services\Application\Handlers\ContactAttributeDefinition\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Spatie\LaravelData\Optional;

class UpsertContactAttributeDefinitionDTO extends BaseDataObject
{
    public function __construct(
        public readonly string          $name,
        public readonly string          $label,
        public readonly string          $type,
        public readonly int             $account_id,
        public readonly array|Optional  $options = new Optional(),
        public readonly int|Optional    $sort_order = new Optional(),
        public readonly bool|Optional   $is_active = new Optional(),
    ) {
    }
}
