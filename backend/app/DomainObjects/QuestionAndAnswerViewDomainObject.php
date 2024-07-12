<?php

namespace HiEvents\DomainObjects;

/**
 * As this is related to a view, and not a table, this was not auto-generated.
 */
class QuestionAndAnswerViewDomainObject extends AbstractDomainObject
{
    final public const SINGULAR_NAME = 'question_and_answer_view';
    final public const PLURAL_NAME = 'question_and_answer_views';

    private int $question_id;
    private ?int $order_id;
    private string $title;
    private ?string $first_name = null;
    private ?string $last_name = null;
    private array|string $answer;
    private string $belongs_to;
    private ?int $attendee_id = null;
    private string $question_type;
    private int $event_id;

    public function getQuestionId(): int
    {
        return $this->question_id;
    }

    public function setQuestionId(int $question_id): QuestionAndAnswerViewDomainObject
    {
        $this->question_id = $question_id;
        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->order_id;
    }

    public function setOrderId(?int $order_id): QuestionAndAnswerViewDomainObject
    {
        $this->order_id = $order_id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): QuestionAndAnswerViewDomainObject
    {
        $this->title = $title;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setLastName(?string $last_name): QuestionAndAnswerViewDomainObject
    {
        $this->last_name = $last_name;
        return $this;
    }

    public function getAnswer(): string|array
    {
        return $this->answer;
    }

    public function setAnswer(array|string $answer): QuestionAndAnswerViewDomainObject
    {
        $this->answer = $answer;
        return $this;
    }

    public function getBelongsTo(): string
    {
        return $this->belongs_to;
    }

    public function setBelongsTo(string $belongs_to): QuestionAndAnswerViewDomainObject
    {
        $this->belongs_to = $belongs_to;
        return $this;
    }

    public function getAttendeeId(): ?int
    {
        return $this->attendee_id;
    }

    public function setAttendeeId(?int $attendee_id): QuestionAndAnswerViewDomainObject
    {
        $this->attendee_id = $attendee_id;
        return $this;
    }

    public function getQuestionType(): string
    {
        return $this->question_type;
    }

    public function setQuestionType(string $question_type): QuestionAndAnswerViewDomainObject
    {
        $this->question_type = $question_type;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setFirstName(?string $first_name): QuestionAndAnswerViewDomainObject
    {
        $this->first_name = $first_name;
        return $this;
    }

    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function setEventId(int $event_id): QuestionAndAnswerViewDomainObject
    {
        $this->event_id = $event_id;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'question_id' => $this->question_id ?? null,
            'order_id' => $this->order_id ?? null,
            'title' => $this->title ?? null,
            'last_name' => $this->last_name ?? null,
            'answer' => $this->answer ?? null,
            'belongs_to' => $this->belongs_to ?? null,
            'attendee_id' => $this->attendee_id ?? null,
            'question_type' => $this->question_type ?? null,
            'first_name' => $this->first_name ?? null,
            'event_id' => $this->event_id ?? null,
        ];
    }
}
