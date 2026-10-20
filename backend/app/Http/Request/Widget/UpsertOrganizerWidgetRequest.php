<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Widget;

use HiEvents\DomainObjects\Enums\WidgetType;

class UpsertOrganizerWidgetRequest extends BaseUpsertWidgetRequest
{
    protected function allowedTypes(): array
    {
        return WidgetType::organizerTypes();
    }
}
