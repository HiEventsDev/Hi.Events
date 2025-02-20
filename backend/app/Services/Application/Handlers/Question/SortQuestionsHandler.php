<?php

namespace HiEvents\Services\Application\Handlers\Question;

use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

readonly class SortQuestionsHandler
{
    public function __construct(
        private QuestionRepositoryInterface $questionRepository,
    )
    {
    }

    public function handle(int $eventId, array $data): void
    {
        $orderedQuestionIds = collect($data)->sortBy('order')->pluck('id')->toArray();

        $questionIdResult = $this->questionRepository->findWhere([
            'event_id' => $eventId,
        ])
            ->map(fn($product) => $product->getId())
            ->toArray();

        $extraInOrdered = array_diff($orderedQuestionIds, $questionIdResult);

        if (!empty($extraInOrdered)) {
            throw new ResourceNotFoundException(
                __('One or more of the ordered question IDs do not exist for the event.')
            );
        }

        $this->questionRepository->sortQuestions($eventId, $orderedQuestionIds);
    }
}
