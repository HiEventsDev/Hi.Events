<?php

namespace HiEvents\Services\Domain\Event\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventSpamCheckContentDTO extends BaseDataObject
{
    /**
     * @param  array<string, string>  $supplementaryContent
     * @param  array<string, string>  $emailTemplates
     * @param  array<string, string>  $renderedEmailTemplates
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $supplementaryContent = [],
        public readonly array $emailTemplates = [],
        public readonly array $renderedEmailTemplates = [],
    ) {}

    /**
     * @return string[]
     */
    public function allHtml(): array
    {
        return array_values(array_filter(
            [$this->description, ...array_values($this->supplementaryContent), ...array_values($this->emailTemplates), ...array_values($this->renderedEmailTemplates)],
            static fn (?string $html): bool => $html !== null && trim($html) !== '',
        ));
    }
}
