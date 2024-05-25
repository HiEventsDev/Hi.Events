<?php

namespace HiEvents\Http\Request\Questions;

use HiEvents\Http\Request\BaseRequest;

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
