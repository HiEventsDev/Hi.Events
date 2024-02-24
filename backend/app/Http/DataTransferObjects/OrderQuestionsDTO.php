<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DataTransferObjects\BaseDTO;

class OrderQuestionsDTO extends BaseDTO
{
    public function __construct(
        public readonly string|int $question_id,
        public readonly array      $response,
    )
    {
    }
}
