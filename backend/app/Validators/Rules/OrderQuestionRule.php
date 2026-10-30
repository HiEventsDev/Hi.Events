<?php

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderQuestionRule extends BaseQuestionRule
{
    public bool $implicit = true;

    protected function belongsTo(): QuestionBelongsTo
    {
        return QuestionBelongsTo::ORDER;
    }

    /**
     * @throws ValidationException
     */
    protected function validateRequiredQuestionArePresent(Collection $orderQuestions): void
    {
        $answers = $this->visibility->answersByQuestionId($orderQuestions->all());

        $requiredQuestionIds = $this->questions
            ->filter(fn (QuestionDomainObject $question) => $question->getRequired())
            ->filter(fn (QuestionDomainObject $question) => ! $question->getIsHidden())
            ->filter(fn (QuestionDomainObject $question) => $this->visibility->isConditionMet($question, $this->questionsById, $answers))
            ->map(fn (QuestionDomainObject $question) => $question->getId());

        if (array_diff($requiredQuestionIds->toArray(), $orderQuestions->pluck('question_id')->toArray())) {
            throw ValidationException::withMessages([
                __('Required questions have not been answered. You may need to reload the page.'),
            ]);
        }
    }

    protected function validateQuestions(mixed $questions): array
    {
        $validationMessages = [];
        $answers = $this->visibility->answersByQuestionId(is_array($questions) ? $questions : []);

        foreach ($questions as $index => $orderQuestion) {
            $questionDomainObject = $this->getQuestionDomainObject($orderQuestion['question_id']);
            $key = 'order.questions.'.$index.'.response';
            $response = $orderQuestion['response'] ?? null;
            $answer = $response['answer'] ?? $response;

            if (! $questionDomainObject) {
                $validationMessages[$key.'.answer'][] = __('This question is outdated. Please reload the page.');

                continue;
            }

            if (! $this->visibility->isConditionMet($questionDomainObject, $this->questionsById, $answers)) {
                continue;
            }

            if (is_null($response) && ! $questionDomainObject->getRequired()) {
                continue;
            }

            if ($questionDomainObject->getRequired()) {
                $validationMessages = $this->validateRequiredFields($questionDomainObject, $response, $key, $validationMessages);
            }

            if (! $questionDomainObject->isAnswerValid($answer)) {
                $validationMessages[$key.'.answer'][] = __('Please select an option');
            }

            $validationMessages = $this->validateResponseLength($questionDomainObject, $response, $key, $validationMessages);
            $validationMessages = $this->validateEligibility($questionDomainObject, $answer, $key, $validationMessages);
        }

        return $validationMessages;
    }
}
