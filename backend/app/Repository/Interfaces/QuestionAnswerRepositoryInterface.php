<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\QuestionAnswerDomainObject;

/**
 * @extends RepositoryInterface<QuestionAnswerDomainObject>
 */
interface QuestionAnswerRepositoryInterface extends RepositoryInterface
{
    public function forceDeleteForAttendee(int $attendeeId): int;
}
