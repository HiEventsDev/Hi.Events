<?php

namespace HiEvents\Mail\Concerns;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Event\UnpublishedEventUpdateMail;

trait ProvidesEventContent
{
    public function getContentEvent(): EventDomainObject
    {
        return $this->event;
    }

    public function withheldEventContentReplacement(): BaseMail
    {
        return new UnpublishedEventUpdateMail;
    }
}
