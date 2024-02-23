<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DomainObjects\Enums\QuestionBelongsTo;
use TicketKitten\DomainObjects\Enums\QuestionTypeEnum;
use TicketKitten\DataTransferObjects\BaseDTO;

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
