<?php

namespace HiEvents\Mail\Concerns;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Mail\BaseMail;

interface ContainsEventContent
{
    public function getContentEvent(): EventDomainObject;

    public function withheldEventContentReplacement(): BaseMail;
}
