<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use Illuminate\Support\Collection;

class QuestionDomainObject extends Generated\QuestionDomainObjectAbstract
{
    public ?Collection $tickets = null;

    public function setTickets(?Collection $tickets): QuestionDomainObject
    {
        $this->tickets = $tickets;
        return $this;
    }

    public function getTickets(): ?Collection
    {
        return $this->tickets;
    }

    public function isMultipleChoice(): bool
    {
        return in_array($this->getType(), [
            QuestionTypeEnum::MULTI_SELECT_DROPDOWN,
            QuestionTypeEnum::CHECKBOX,
        ], true);
    }

    public function setOptions(array|string|null $options): self
    {
        if (is_array($options)) {
            $options = array_filter(array_unique($options));
        }

        $this->options = $options;
        return $this;
    }

}
