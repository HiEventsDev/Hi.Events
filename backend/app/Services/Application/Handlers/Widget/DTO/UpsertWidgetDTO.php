<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Widget\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\WidgetType;

class UpsertWidgetDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $account_id,
        public readonly int $organizer_id,
        public readonly ?int $event_id,
        public readonly string $name,
        public readonly WidgetType $type,
        public readonly array $settings,
        public readonly ?int $widget_id = null,
    ) {}
}
