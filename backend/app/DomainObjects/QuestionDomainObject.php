<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use Illuminate\Support\Collection;

class QuestionDomainObject extends Generated\QuestionDomainObjectAbstract
{
    public ?Collection $products = null;

    public function setProducts(?Collection $products): QuestionDomainObject
    {
        $this->products = $products;

        return $this;
    }

    public function getProducts(): ?Collection
    {
        return $this->products;
    }

    public function isPreDefinedChoice(): bool
    {
        return in_array($this->getType(), [
            QuestionTypeEnum::MULTI_SELECT_DROPDOWN->name,
            QuestionTypeEnum::CHECKBOX->name,
            QuestionTypeEnum::RADIO->name,
            QuestionTypeEnum::DROPDOWN->name,
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

    public function getConditionValuesList(): array
    {
        return $this->toStringList($this->getConditionValues());
    }

    public function getDisqualifyingOptionsList(): array
    {
        return $this->toStringList($this->getDisqualifyingOptions());
    }

    public function getOptionsList(): array
    {
        return $this->toStringList($this->getOptions());
    }

    public function isDisqualifyingAnswer(mixed $answer): bool
    {
        if ($answer === null) {
            return false;
        }

        return array_intersect($this->toStringList($answer), $this->getDisqualifyingOptionsList()) !== [];
    }

    public function isAnswerValid(mixed $answer): bool
    {
        if (! isset($answer)) {
            return false;
        }

        if (! $this->isPreDefinedChoice()) {
            return true;
        }

        if (is_string($answer)) {
            return in_array($answer, $this->getOptions(), true);
        }

        return array_diff((array) $answer, $this->getOptions()) === [];
    }

    private function toStringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_map('strval', array_filter((array) $value, 'is_scalar')));
    }
}
