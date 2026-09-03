<?php

namespace HiEvents\Services\Domain\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventSpamCheckResultDTO extends BaseDataObject
{
    /**
     * @param  string[]  $reasons
     */
    public function __construct(
        public readonly bool $isSpam,
        public readonly float $confidence,
        public readonly array $reasons,
        public readonly string $model,
    ) {}

    public function toVerdictArray(): array
    {
        return [
            'is_spam' => $this->isSpam,
            'confidence' => $this->confidence,
            'reasons' => $this->reasons,
            'model' => $this->model,
        ];
    }
}
