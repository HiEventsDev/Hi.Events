<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Enums\QuestionTypeEnum;

class QuestionAnswerFormatter
{
    public function getAnswerAsText(string|array $answer, QuestionTypeEnum $questionType): string
    {
        if ($questionType === QuestionTypeEnum::ADDRESS) {
            $addressLines = [
                $answer['address_line_1'] ?? null,
                $answer['address_line_2'] ?? null,
                $answer['city'] ?? null,
                $answer['state_or_region'] ?? null,
                $answer['zip_or_postal_code'] ?? null,
                $answer['country'] ?? null,
            ];

            return implode(', ', array_filter($addressLines, static function ($line) {
                return !empty($line);
            }));
        }

        if (is_array($answer)) {
            return implode(', ', $answer);
        }

        return $answer;
    }
}
