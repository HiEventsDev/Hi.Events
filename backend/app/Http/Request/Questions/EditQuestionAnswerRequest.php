<?php

namespace HiEvents\Http\Request\Questions;

use HiEvents\Http\Request\BaseRequest;

class EditQuestionAnswerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'answer' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!is_string($value) && !is_array($value)) {
                        $fail("The {$attribute} must be a string or an array.");
                    }
                }
            ],
        ];
    }
}
