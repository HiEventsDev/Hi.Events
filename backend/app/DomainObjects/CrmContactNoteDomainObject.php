<?php

namespace HiEvents\DomainObjects;

class CrmContactNoteDomainObject extends Generated\CrmContactNoteDomainObjectAbstract
{
    private ?string $authorName = null;

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }
}
