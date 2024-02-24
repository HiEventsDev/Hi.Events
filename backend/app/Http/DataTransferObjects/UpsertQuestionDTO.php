<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DataTransferObjects\BaseDTO;

class UpsertQuestionDTO extends BaseDTO
{
    public function __construct(
        public string            $title,
        public QuestionTypeEnum  $type,
        public bool              $required,
        public ?array            $options,
        public int               $event_id,
        public array             $ticket_ids,
        public bool              $is_hidden,
        public QuestionBelongsTo $belongs_to,
    )
    {
    }
}
