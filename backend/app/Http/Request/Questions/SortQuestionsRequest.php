<?php

namespace TicketKitten\Http\Request\Questions;

use TicketKitten\Http\Request\BaseRequest;

class SortQuestionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            '*.id' => 'integer|required',
            '*.order' => 'integer|required',
        ];
    }
}
