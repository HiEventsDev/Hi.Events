<?php

namespace HiEvents\Mail\Concerns;

use HiEvents\DomainObjects\OrganizerDomainObject;

interface SentOnBehalfOfOrganizer
{
    public function getContentOrganizer(): ?OrganizerDomainObject;
}
