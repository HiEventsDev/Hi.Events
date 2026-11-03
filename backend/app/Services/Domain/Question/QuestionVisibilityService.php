<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;

class QuestionVisibilityService
{
    private const MAX_CONDITION_DEPTH = 10;

    /**
     * @param  Collection<int, QuestionDomainObject>  $questions
     * @return Collection<int, QuestionDomainObject>
     */
    public function keyById(Collection $questions): Collection
    {
        return $questions->keyBy(fn (QuestionDomainObject $question) => $question->getId());
    }

    /**
     * @return array<int, mixed>
     */
    public function answersByQuestionId(iterable $entries): array
    {
        $answers = [];

        foreach ($entries as $entry) {
            $questionId = is_array($entry) ? ($entry['question_id'] ?? null) : null;

            if (! is_numeric($questionId)) {
                continue;
            }

            $answers[(int) $questionId] = $this->extractAnswer($entry['response'] ?? null);
        }

        return $answers;
    }

    public function extractAnswer(mixed $response): mixed
    {
        return is_array($response) && array_key_exists('answer', $response) ? $response['answer'] : $response;
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questionsById
     * @param  array<int, mixed>  $orderAnswers
     * @param  array<int, mixed>  $attendeeAnswers
     */
    public function isConditionMet(
        QuestionDomainObject $question,
        Collection $questionsById,
        array $orderAnswers,
        array $attendeeAnswers = [],
        ?int $productId = null,
    ): bool {
        $current = $question;

        for ($depth = 0; $depth < self::MAX_CONDITION_DEPTH; $depth++) {
            $parentId = $current->getConditionQuestionId();

            if ($parentId === null) {
                return true;
            }

            /** @var QuestionDomainObject|null $parent */
            $parent = $questionsById->get($parentId);

            if ($parent === null || $parent->getIsHidden()) {
                return false;
            }

            $parentIsAttendeeQuestion = $parent->getBelongsTo() === QuestionBelongsTo::PRODUCT->name;

            if ($parentIsAttendeeQuestion && ! $this->appliesToProduct($parent, $productId)) {
                return false;
            }

            $parentAnswer = $parentIsAttendeeQuestion
                ? ($attendeeAnswers[$parentId] ?? null)
                : ($orderAnswers[$parentId] ?? null);

            if (! $this->answerMatches($parentAnswer, $current->getConditionValuesList())) {
                return false;
            }

            $current = $parent;
        }

        return false;
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questionsById
     * @param  array<int, mixed>  $orderAnswers
     * @param  array<int, mixed>  $attendeeAnswers
     */
    public function shouldKeepAnswer(
        int $questionId,
        Collection $questionsById,
        array $orderAnswers,
        array $attendeeAnswers = [],
        ?int $productId = null,
    ): bool {
        $question = $questionsById->get($questionId);

        return $question === null || $this->isConditionMet($question, $questionsById, $orderAnswers, $attendeeAnswers, $productId);
    }

    private function appliesToProduct(QuestionDomainObject $question, ?int $productId): bool
    {
        if ($productId === null) {
            return false;
        }

        return (bool) $question->getProducts()?->contains(
            fn (ProductDomainObject $product) => $product->getId() === $productId
        );
    }

    private function answerMatches(mixed $answer, array $conditionValues): bool
    {
        if ($answer === null || $answer === '' || $conditionValues === []) {
            return false;
        }

        $selected = array_map('strval', array_filter((array) $answer, 'is_scalar'));

        return array_intersect($selected, $conditionValues) !== [];
    }
}
