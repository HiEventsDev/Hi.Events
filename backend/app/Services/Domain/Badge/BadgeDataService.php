<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\Generated\QuestionAnswerDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeLineDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeRibbonDTO;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Illuminate\Support\Collection;

class BadgeDataService
{
    private const NAME_SOURCES = [
        BadgeFieldSource::FIRST_NAME,
        BadgeFieldSource::LAST_NAME,
        BadgeFieldSource::FULL_NAME,
    ];

    public function __construct(
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly QuestionAnswerRepositoryInterface $questionAnswerRepository,
        private readonly QuestionAnswerFormatter $questionAnswerFormatter,
    ) {}

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @return Collection<int, BadgeDTO>
     */
    public function buildBadges(int $eventId, Collection $attendees, BadgeDesignDTO $design): Collection
    {
        if ($attendees->isEmpty()) {
            return collect();
        }

        $questions = $this->loadQuestions($eventId, $design);
        $answers = $this->loadAnswers($attendees, $questions);

        return $attendees->map(fn (AttendeeDomainObject $attendee) => new BadgeDTO(
            attendee_id: $attendee->getId(),
            attendee_public_id: $attendee->getPublicId(),
            order_id: $attendee->getOrderId(),
            product_id: $attendee->getProductId(),
            event_occurrence_id: $attendee->getEventOccurrenceId(),
            status: $attendee->getStatus(),
            first_name: $attendee->getFirstName(),
            last_name: $attendee->getLastName(),
            lines: $this->resolveLines($attendee, $design, $questions, $answers),
            ribbon: $this->resolveRibbon($attendee, $design),
            seat_label: $design->show_seat ? $attendee->getSeatLabel() : null,
            badge_printed_at: $attendee->getBadgePrintedAt(),
            badge_print_count: $attendee->getBadgePrintCount(),
            print_status: $attendee->getBadgePrintStatus(),
        ))->values();
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questions
     * @param  Collection<string, QuestionAnswerDomainObject>  $answers
     * @return BadgeLineDTO[]
     */
    private function resolveLines(
        AttendeeDomainObject $attendee,
        BadgeDesignDTO $design,
        Collection $questions,
        Collection $answers,
    ): array {
        $lines = [];

        foreach ($design->lines as $line) {
            $text = $this->resolveText($attendee, $line, $questions, $answers);

            if (($text === null || trim($text) === '') && ! in_array($line->source, self::NAME_SOURCES, true)) {
                continue;
            }

            $lines[] = new BadgeLineDTO(
                source: $line->source,
                size: $line->size,
                question_id: $line->question_id,
                text: trim((string) $text),
            );
        }

        return $lines;
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questions
     * @param  Collection<string, QuestionAnswerDomainObject>  $answers
     */
    private function resolveText(
        AttendeeDomainObject $attendee,
        BadgeLineDTO $line,
        Collection $questions,
        Collection $answers,
    ): ?string {
        return match ($line->source) {
            BadgeFieldSource::FIRST_NAME => $attendee->getFirstName(),
            BadgeFieldSource::LAST_NAME => $attendee->getLastName(),
            BadgeFieldSource::FULL_NAME => trim($attendee->getFirstName().' '.$attendee->getLastName()),
            BadgeFieldSource::TICKET_TYPE => $attendee->getProduct()?->getTitle(),
            BadgeFieldSource::OCCURRENCE_DATE => $attendee->getEventOccurrence()?->getStartDate(),
            BadgeFieldSource::CUSTOM_TEXT => $line->text,
            BadgeFieldSource::QUESTION => $this->resolveAnswer($attendee, $line->question_id, $questions, $answers),
        };
    }

    /**
     * @param  Collection<int, QuestionDomainObject>  $questions
     * @param  Collection<string, QuestionAnswerDomainObject>  $answers
     */
    private function resolveAnswer(
        AttendeeDomainObject $attendee,
        ?int $questionId,
        Collection $questions,
        Collection $answers,
    ): ?string {
        /** @var QuestionDomainObject|null $question */
        $question = $questions->get($questionId);
        if ($question === null) {
            return null;
        }

        $isOrderQuestion = $question->getBelongsTo() === QuestionBelongsTo::ORDER->name;

        /** @var QuestionAnswerDomainObject|null $answer */
        $answer = $answers->get($isOrderQuestion
            ? self::answerKey($questionId, 'order', $attendee->getOrderId())
            : self::answerKey($questionId, 'attendee', $attendee->getId()));

        if ($answer === null || $answer->getAnswer() === null) {
            return null;
        }

        return $this->questionAnswerFormatter->getAnswerAsText(
            $answer->getAnswer(),
            QuestionTypeEnum::fromName($question->getType()),
        );
    }

    private function resolveRibbon(AttendeeDomainObject $attendee, BadgeDesignDTO $design): ?BadgeRibbonDTO
    {
        if (! $design->show_ribbon) {
            return null;
        }

        $configured = $design->ribbonForProduct($attendee->getProductId());
        $label = $configured?->label ?: $attendee->getProduct()?->getTitle();

        return new BadgeRibbonDTO(
            product_id: $attendee->getProductId(),
            label: $label,
            color: $configured?->color ?? $design->accent_color,
        );
    }

    /**
     * @return Collection<int, QuestionDomainObject>
     */
    private function loadQuestions(int $eventId, BadgeDesignDTO $design): Collection
    {
        $questionIds = $design->questionIds();
        if ($questionIds === []) {
            return collect();
        }

        return $this->questionRepository
            ->findWhereIn(QuestionDomainObjectAbstract::ID, $questionIds, [QuestionDomainObjectAbstract::EVENT_ID => $eventId])
            ->keyBy(fn (QuestionDomainObject $question) => $question->getId());
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @param  Collection<int, QuestionDomainObject>  $questions
     * @return Collection<string, QuestionAnswerDomainObject>
     */
    private function loadAnswers(Collection $attendees, Collection $questions): Collection
    {
        if ($questions->isEmpty()) {
            return collect();
        }

        return $this->questionAnswerRepository->findWhereIn(
            QuestionAnswerDomainObjectAbstract::ORDER_ID,
            $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getOrderId())->unique()->values()->all(),
            [[QuestionAnswerDomainObjectAbstract::QUESTION_ID, 'in', $questions->keys()->all()]],
        )->keyBy(fn (QuestionAnswerDomainObject $answer) => $answer->getAttendeeId() === null
            ? self::answerKey($answer->getQuestionId(), 'order', $answer->getOrderId())
            : self::answerKey($answer->getQuestionId(), 'attendee', $answer->getAttendeeId()));
    }

    private static function answerKey(int $questionId, string $scope, int $id): string
    {
        return $questionId.':'.$scope.':'.$id;
    }
}
