<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\OutgoingMessageDomainObject;
use HiEvents\Models\OutgoingMessage;
use HiEvents\Repository\Interfaces\OutgoingMessageRepositoryInterface;

class OutgoingMessageRepository extends BaseRepository implements OutgoingMessageRepositoryInterface
{
    protected function getModel(): string
    {
        return OutgoingMessage::class;
    }

    public function getDomainObject(): string
    {
        return OutgoingMessageDomainObject::class;
    }
}
