<?php

namespace HiEvents\Models;

class CrmBulkOperation extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'tag_ids' => 'array',
        ];
    }
}
