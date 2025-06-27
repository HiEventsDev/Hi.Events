<?php

namespace HiEvents\Services\Application\Handlers\User\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ConfirmEmailWithCodeDTO extends BaseDataObject
{
    public string $code;
    public int $userId;
    public int $accountId;
}
