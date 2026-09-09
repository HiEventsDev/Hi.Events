<?php

namespace HiEvents\Services\Domain\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventSpamCheckContentDTO extends BaseDataObject
{
    /**
     * @param  array<string, string>  $supplementaryContent
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $supplementaryContent = [],
    ) {}

    /**
     * @return string[]
     */
    public function allHtml(): array
    {
        return array_values(array_filter(
            [$this->description, ...array_values($this->supplementaryContent)],
            static fn (?string $html): bool => $html !== null && trim($html) !== '',
        ));
    }
}
