<?php

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderQuestionRule extends BaseQuestionRule
{
    /**
     * @throws ValidationException
     */
    protected function validateRequiredQuestionArePresent(Collection $orderQuestions): void
    {
        $requiredQuestionIds = $this->questions
            ->filter(fn(QuestionDomainObject $question) => $question->getRequired())
            ->filter(fn(QuestionDomainObject $question) => !$question->getIsHidden())
            ->map(fn(QuestionDomainObject $question) => $question->getId());

        if (array_diff($requiredQuestionIds->toArray(), $orderQuestions->pluck('question_id')->toArray())) {
            throw ValidationException::withMessages([
                'Required questions have not been answered. You may need to reload the page.'
            ]);
        }
    }

    protected function validateQuestions(mixed $questions): array
    {
        $validationMessages = [];
        foreach ($questions as $index => $orderQuestion) {
            $questionDomainObject = $this->getQuestionDomainObject($orderQuestion['question_id']);
            $key = 'order.questions.' . $index . '.response';
            $response = $orderQuestion['response'] ?? null;

            if (!$questionDomainObject) {
                $validationMessages[$key . '.answer'][] = 'This question is outdated. Please reload the page.';
                continue;
            }

            if (is_null($response) && !$questionDomainObject->getRequired()) {
                continue;
            }

            if ($questionDomainObject->getRequired()) {
                $validationMessages = $this->validateRequiredFields($questionDomainObject, $response, $key, $validationMessages);
            }

            if (!$this->isAnswerValid($questionDomainObject, $response)) {
                $validationMessages[$key . '.answer'][] = 'Please select an option';
            }

            $validationMessages = $this->validateResponseLength($questionDomainObject, $response, $key, $validationMessages);
        }

        return $validationMessages;
    }
}
