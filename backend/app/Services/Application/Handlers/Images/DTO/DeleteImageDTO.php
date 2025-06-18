<?php

namespace HiEvents\Services\Application\Handlers\Images\DTO;

class DeleteImageDTO
{
    public function __construct(
        public readonly int $imageId,
        public readonly int $userId,
        public readonly int $accountId,
    )
    {
    }
}
