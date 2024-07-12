<?php

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendeeQuestionRule extends BaseQuestionRule
{
    /**
     * @throws ValidationException
     */
    protected function validateRequiredQuestionArePresent(Collection $orderAttendees): void
    {
        foreach ($orderAttendees as $attendee) {
            $ticketId = $this->getTicketIdFromTicketPriceId($attendee['ticket_price_id']);
            $questions = $attendee['questions'] ?? [];

            $requiredQuestionIds = $this->questions
                ->filter(function (QuestionDomainObject $question) use ($ticketId) {
                    return $question->getRequired()
                        && !$question->getIsHidden()
                        && $question->getTickets()?->map(fn($ticket) => $ticket->getId())->contains($ticketId);
                })
                ->map(fn(QuestionDomainObject $question) => $question->getId());

            if (array_diff($requiredQuestionIds->toArray(), collect($questions)->pluck('question_id')->toArray())) {
                throw ValidationException::withMessages([
                    __('Required questions have not been answered. You may need to reload the page.')
                ]);
            }
        }
    }

    protected function validateQuestions(mixed $attendees): array
    {
        $validationMessages = [];

        foreach ($attendees as $attendeeIndex => $attendee) {
            $questions = $attendee['questions'] ?? [];
            foreach ($questions as $questionIndex => $question) {
                $questionDomainObject = $this->getQuestionDomainObject($question['question_id'] ?? null);
                $key = 'attendees.' . $attendeeIndex . '.questions.' . $questionIndex . '.response';
                $response = empty($question['response']) ? null : $question['response'];

                if (!$questionDomainObject) {
                    $validationMessages[$key . '.answer'][] = __('This question is outdated. Please reload the page.');
                    continue;
                }

                if (is_null($response) && !$questionDomainObject->getRequired()) {
                    continue;
                }

                if ($questionDomainObject->getRequired()) {
                    $validationMessages = $this->validateRequiredFields($questionDomainObject, $response, $key, $validationMessages);
                }

                if (!$this->isAnswerValid($questionDomainObject, $response)) {
                    $validationMessages[$key . '.answer'][] = __('Please select an option');
                }

                $validationMessages = $this->validateResponseLength($questionDomainObject, $response, $key, $validationMessages);
            }
        }

        return $validationMessages;
    }
}
