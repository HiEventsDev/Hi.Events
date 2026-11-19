<?php

namespace HiEvents\Models;

class CrmImport extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'skipped_reasons' => 'array',
        ];
    }
}
