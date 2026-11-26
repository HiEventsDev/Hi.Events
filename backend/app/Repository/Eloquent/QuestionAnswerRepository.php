<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\Models\QuestionAnswer;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;

/**
 * @extends BaseRepository<QuestionAnswerDomainObject>
 */
class QuestionAnswerRepository extends BaseRepository implements QuestionAnswerRepositoryInterface
{
    protected function getModel(): string
    {
        return QuestionAnswer::class;
    }

    public function getDomainObject(): string
    {
        return QuestionAnswerDomainObject::class;
    }

    public function forceDeleteForAttendee(int $attendeeId): int
    {
        return $this->runQuery(
            fn () => QuestionAnswer::withTrashed()->where('attendee_id', $attendeeId)->forceDelete()
        );
    }
}
