<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;

class QuestionAnswerDomainObject extends Generated\QuestionAnswerDomainObjectAbstract
{
    private ?OrderDomainObjectAbstract $order = null;

    private ?QuestionDomainObject $question = null;

    public function setOrder(?OrderDomainObjectAbstract $order): QuestionAnswerDomainObject
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): ?OrderDomainObjectAbstract
    {
        return $this->order;
    }

    public function setQuestion(?QuestionDomainObject $question): QuestionAnswerDomainObject
    {
        $this->question = $question;

        return $this;
    }

    public function getQuestion(): ?QuestionDomainObject
    {
        return $this->question;
    }
}
