<?php

namespace HiEvents\DomainObjects;

/**
 * As this is related to a view, and not a table, this was not auto-generated.
 */
class QuestionAndAnswerViewDomainObject extends AbstractDomainObject
{
    final public const SINGULAR_NAME = 'question_and_answer_view';
    final public const PLURAL_NAME = 'question_and_answer_views';

    private ?int $product_id;
    private ?string $product_title;
    private int $question_id;
    private ?int $order_id;
    private ?string $order_first_name;
    private ?string $order_last_name;
    private ?string $order_email;
    private ?string $order_public_id;
    private string $title;
    private bool $question_required;
    private ?string $question_description = null;
    private ?int $attendee_id = null;
    private ?string $attendee_public_id = null;
    private ?string $first_name = null;
    private ?string $last_name = null;
    private ?string $attendee_email = null;
    private array|string $answer;
    private string $belongs_to;
    private string $question_type;
    private int $event_id;
    private int $question_answer_id;
    private ?array $question_options = null;

    private ?AttendeeDomainObject $attendee = null;

    private ?QuestionDomainObject $question = null;

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

    public function getProductId(): ?int
    {
        return $this->product_id;
    }

    public function setProductId(?int $product_id): QuestionAndAnswerViewDomainObject
    {
        $this->product_id = $product_id;
        return $this;
    }

    public function getProductTitle(): ?string
    {
        return $this->product_title;
    }

    public function setProductTitle(?string $product_title): QuestionAndAnswerViewDomainObject
    {
        $this->product_title = $product_title;
        return $this;
    }

    public function getAttendee(): ?AttendeeDomainObject
    {
        return $this->attendee;
    }

    public function setAttendee(?AttendeeDomainObject $attendee): static
    {
        $this->attendee = $attendee;

        return $this;
    }

    public function getQuestion(): ?QuestionDomainObject
    {
        return $this->question;
    }

    public function setQuestion(?QuestionDomainObject $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getQuestionAnswerId(): int
    {
        return $this->question_answer_id;
    }

    public function setQuestionAnswerId(int $question_answer_id): QuestionAndAnswerViewDomainObject
    {
        $this->question_answer_id = $question_answer_id;

        return $this;
    }

    public function getQuestionDescription(): ?string
    {
        return $this->question_description;
    }

    public function setQuestionDescription(?string $question_description): QuestionAndAnswerViewDomainObject
    {
        $this->question_description = $question_description;

        return $this;
    }

    public function getQuestionRequired(): bool
    {
        return $this->question_required;
    }

    public function setQuestionRequired(bool $question_required): QuestionAndAnswerViewDomainObject
    {
        $this->question_required = $question_required;

        return $this;
    }

    public function getQuestionOptions(): ?array
    {
        return $this->question_options;
    }

    public function setQuestionOptions(?array $question_options): QuestionAndAnswerViewDomainObject
    {
        $this->question_options = $question_options;

        return $this;
    }

    public function getAttendeePublicId(): ?string
    {
        return $this->attendee_public_id;
    }

    public function setAttendeePublicId(?string $attendee_public_id): QuestionAndAnswerViewDomainObject
    {
        $this->attendee_public_id = $attendee_public_id;

        return $this;
    }

    public function getOrderFirstName(): ?string
    {
        return $this->order_first_name;
    }

    public function setOrderFirstName(?string $order_first_name): QuestionAndAnswerViewDomainObject
    {
        $this->order_first_name = $order_first_name;

        return $this;
    }

    public function getOrderLastName(): ?string
    {
        return $this->order_last_name;
    }

    public function setOrderLastName(?string $order_last_name): QuestionAndAnswerViewDomainObject
    {
        $this->order_last_name = $order_last_name;

        return $this;
    }

    public function getOrderEmail(): ?string
    {
        return $this->order_email;
    }

    public function setOrderEmail(?string $order_email): QuestionAndAnswerViewDomainObject
    {
        $this->order_email = $order_email;

        return $this;
    }

    public function getOrderPublicId(): ?string
    {
        return $this->order_public_id;
    }

    public function setOrderPublicId(?string $order_public_id): QuestionAndAnswerViewDomainObject
    {
        $this->order_public_id = $order_public_id;

        return $this;
    }

    public function getAttendeeEmail(): ?string
    {
        return $this->attendee_email;
    }

    public function setAttendeeEmail(?string $attendee_email): QuestionAndAnswerViewDomainObject
    {
        $this->attendee_email = $attendee_email;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'question_id' => $this->question_id ?? null,
            'order_id' => $this->order_id ?? null,
            'title' => $this->title ?? null,
            'question_description' => $this->question_description ?? null,
            'question_required' => $this->question_required ?? null,
            'last_name' => $this->last_name ?? null,
            'answer' => $this->answer ?? null,
            'belongs_to' => $this->belongs_to ?? null,
            'attendee_id' => $this->attendee_id ?? null,
            'attendee_public_id' => $this->attendee_public_id ?? null,
            'question_type' => $this->question_type ?? null,
            'first_name' => $this->first_name ?? null,
            'event_id' => $this->event_id ?? null,
            'product_id' => $this->product_id ?? null,
            'product_title' => $this->product_title ?? null,
            'question_answer_id' => $this->question_answer_id ?? null,
            'question_options' => $this->question_options ?? null,
            'attendee_email' => $this->attendee_email ?? null,
            'order_first_name' => $this->order_first_name ?? null,
            'order_last_name' => $this->order_last_name ?? null,
            'order_email' => $this->order_email ?? null,
            'order_public_id' => $this->order_public_id ?? null,
        ];
    }
}
