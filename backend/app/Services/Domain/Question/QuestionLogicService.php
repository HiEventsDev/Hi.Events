<?php

namespace HiEvents\Services\Domain\Question;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\InvalidQuestionLogicException;
use Illuminate\Support\Collection;

class QuestionLogicService
{
    public const MAX_FOLLOW_UP_DEPTH = 5;

    public function normalize(QuestionDomainObject $question): QuestionDomainObject
    {
        if ($question->getConditionQuestionId() === null) {
            $question->setConditionValues(null);
        } else {
            $question->setConditionValues(array_values(array_unique($question->getConditionValuesList())));
        }

        $disqualifyingOptions = array_values(array_unique($question->getDisqualifyingOptionsList()));

        if ($disqualifyingOptions === []) {
            return $question
                ->setDisqualifyingOptions(null)
                ->setDisqualificationMessage(null);
        }

        return $question
            ->setDisqualifyingOptions($disqualifyingOptions)
            ->setDisqualificationMessage(trim((string) $question->getDisqualificationMessage()))
            ->setRequired(true);
    }

    /**
     * @param  array<int, array{from: string, to: string}>  $renames
     */
    public function renameConditionValues(QuestionDomainObject $dependent, array $renames): QuestionDomainObject
    {
        return (clone $dependent)->setConditionValues($this->renameValues($dependent->getConditionValuesList(), $renames));
    }

    /**
     * @param  string[]  $values
     * @param  array<int, array{from: string, to: string}>  $renames
     * @return string[]
     */
    public function renameValues(array $values, array $renames): array
    {
        $renamed = array_column($renames, 'to', 'from');

        return array_values(array_unique(array_map(fn (string $value) => $renamed[$value] ?? $value, $values)));
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $eventQuestions
     * @param  Collection<int, QuestionDomainObject>  $dependents
     *
     * @throws InvalidQuestionLogicException
     */
    public function validate(QuestionDomainObject $question, Collection $eventQuestions, Collection $dependents): void
    {
        $this->validateCondition($question, $eventQuestions);
        $this->validateDisqualification($question);
        $this->validateDependents($question, $dependents);
    }

    /**
     * @throws InvalidQuestionLogicException
     */
    private function validateCondition(QuestionDomainObject $question, Collection $eventQuestions): void
    {
        $parentId = $question->getConditionQuestionId();

        if ($parentId === null) {
            return;
        }

        $questionsById = $eventQuestions->keyBy(fn (QuestionDomainObject $eventQuestion) => $eventQuestion->getId());

        /** @var QuestionDomainObject|null $parent */
        $parent = $questionsById->get($parentId);

        if ($parent === null) {
            $this->fail(QuestionDomainObjectAbstract::CONDITION_QUESTION_ID, __('The question this depends on could not be found.'));
        }

        if ($this->isSameQuestion($question, $parent)) {
            $this->fail(QuestionDomainObjectAbstract::CONDITION_QUESTION_ID, __('A question cannot depend on itself.'));
        }

        if (! $parent->isPreDefinedChoice()) {
            $this->fail(QuestionDomainObjectAbstract::CONDITION_QUESTION_ID, __('Follow-up questions can only depend on a question with options.'));
        }

        if ($question->getBelongsTo() === QuestionBelongsTo::ORDER->name
            && $parent->getBelongsTo() === QuestionBelongsTo::PRODUCT->name) {
            $this->fail(QuestionDomainObjectAbstract::CONDITION_QUESTION_ID, __('Questions asked once per order can only depend on other order questions.'));
        }

        $this->validateAncestry($question, $parent, $questionsById);

        $conditionValues = $question->getConditionValuesList();

        if ($conditionValues === []) {
            $this->fail(QuestionDomainObjectAbstract::CONDITION_VALUES, __('Choose at least one answer that shows this question.'));
        }

        if (array_diff($conditionValues, $parent->getOptionsList()) !== []) {
            $this->fail(QuestionDomainObjectAbstract::CONDITION_VALUES, __('Choose answers from the options of the question this depends on.'));
        }
    }

    /**
     * @throws InvalidQuestionLogicException
     */
    private function validateAncestry(QuestionDomainObject $question, QuestionDomainObject $parent, Collection $questionsById): void
    {
        $questionId = $this->idOf($question);
        $depth = 1;
        $ancestorId = $parent->getConditionQuestionId();

        while ($ancestorId !== null) {
            if ($ancestorId === $questionId) {
                $this->fail(QuestionDomainObjectAbstract::CONDITION_QUESTION_ID, __('This would create a loop of follow-up questions.'));
            }

            if (++$depth > self::MAX_FOLLOW_UP_DEPTH) {
                $this->failTooDeep();
            }

            $ancestorId = $questionsById->get($ancestorId)?->getConditionQuestionId();
        }

        if ($depth + $this->followUpDepth($questionId, $questionsById) > self::MAX_FOLLOW_UP_DEPTH) {
            $this->failTooDeep();
        }
    }

    private function followUpDepth(?int $questionId, Collection $questionsById): int
    {
        if ($questionId === null) {
            return 0;
        }

        $depth = 0;
        $parentIds = [$questionId];
        $seenIds = [$questionId];

        while (true) {
            $childIds = $questionsById
                ->filter(fn (QuestionDomainObject $candidate) => in_array($candidate->getConditionQuestionId(), $parentIds, true))
                ->map(fn (QuestionDomainObject $candidate) => $candidate->getId())
                ->diff($seenIds)
                ->values()
                ->all();

            if ($childIds === []) {
                return $depth;
            }

            $depth++;
            $seenIds = [...$seenIds, ...$childIds];
            $parentIds = $childIds;
        }
    }

    /**
     * @throws InvalidQuestionLogicException
     */
    private function failTooDeep(): never
    {
        $this->fail(
            QuestionDomainObjectAbstract::CONDITION_QUESTION_ID,
            __('Follow-up questions can be nested at most :max levels deep.', ['max' => self::MAX_FOLLOW_UP_DEPTH]),
        );
    }

    /**
     * @throws InvalidQuestionLogicException
     */
    private function validateDisqualification(QuestionDomainObject $question): void
    {
        $disqualifyingOptions = $question->getDisqualifyingOptionsList();

        if ($disqualifyingOptions === []) {
            return;
        }

        if (! $question->isPreDefinedChoice()) {
            $this->fail(QuestionDomainObjectAbstract::DISQUALIFYING_OPTIONS, __('Only questions with options can have ineligible answers.'));
        }

        $options = $question->getOptionsList();

        if (array_diff($disqualifyingOptions, $options) !== []) {
            $this->fail(QuestionDomainObjectAbstract::DISQUALIFYING_OPTIONS, __('Ineligible answers must be options of this question.'));
        }

        if (array_diff($options, $disqualifyingOptions) === []) {
            $this->fail(QuestionDomainObjectAbstract::DISQUALIFYING_OPTIONS, __('At least one answer must let people register.'));
        }

        if (trim((string) $question->getDisqualificationMessage()) === '') {
            $this->fail(QuestionDomainObjectAbstract::DISQUALIFICATION_MESSAGE, __('Enter the message shown to people who are not eligible.'));
        }
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $dependents
     *
     * @throws InvalidQuestionLogicException
     */
    private function validateDependents(QuestionDomainObject $question, Collection $dependents): void
    {
        if ($dependents->isEmpty()) {
            return;
        }

        if (! $question->isPreDefinedChoice()) {
            $this->fail(QuestionDomainObjectAbstract::TYPE, __('Follow-up questions depend on this question. Remove their condition before changing its type.'));
        }

        $hasOrderDependents = $dependents->contains(
            fn (QuestionDomainObject $dependent) => $dependent->getBelongsTo() === QuestionBelongsTo::ORDER->name
        );

        if ($hasOrderDependents && $question->getBelongsTo() === QuestionBelongsTo::PRODUCT->name) {
            $this->fail(QuestionDomainObjectAbstract::BELONGS_TO, __('Follow-up questions asked once per order depend on this question, so it must also be asked once per order.'));
        }

        $options = $question->getOptionsList();

        foreach ($dependents as $dependent) {
            $missing = array_values(array_diff($dependent->getConditionValuesList(), $options));

            if ($missing !== []) {
                $this->fail(
                    QuestionDomainObjectAbstract::OPTIONS,
                    __('":option" is used by the follow-up question ":question". Rename the option instead of removing it, or update the follow-up first.', [
                        'option' => $missing[0],
                        'question' => $dependent->getTitle(),
                    ]),
                );
            }
        }
    }

    private function isSameQuestion(QuestionDomainObject $question, QuestionDomainObject $other): bool
    {
        return $this->idOf($question) === $other->getId();
    }

    private function idOf(QuestionDomainObject $question): ?int
    {
        return $question->toArray()[QuestionDomainObjectAbstract::ID] ?? null;
    }

    /**
     * @throws InvalidQuestionLogicException
     */
    private function fail(string $field, string $message): never
    {
        throw new InvalidQuestionLogicException($field, $message);
    }
}
