<?php

namespace HiEvents\DomainObjects;

class ContactAttributeDefinitionDomainObject extends Generated\ContactAttributeDefinitionDomainObjectAbstract
{
    private ?int $linkedQuestionCount = null;

    private ?int $linkedEventCount = null;

    public function setLinkedQuestionCount(int $count): self
    {
        $this->linkedQuestionCount = $count;

        return $this;
    }

    public function getLinkedQuestionCount(): ?int
    {
        return $this->linkedQuestionCount;
    }

    public function setLinkedEventCount(int $count): self
    {
        $this->linkedEventCount = $count;

        return $this;
    }

    public function getLinkedEventCount(): ?int
    {
        return $this->linkedEventCount;
    }
}
