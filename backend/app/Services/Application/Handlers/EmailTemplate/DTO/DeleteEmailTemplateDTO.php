<?php

namespace HiEvents\Services\Application\Handlers\EmailTemplate\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DeleteEmailTemplateDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $id,
        public readonly int $account_id,
    )
    {
    }
}
