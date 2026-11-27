<?php

namespace HiEvents\Models;

class CrmSegment extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'definition' => 'array',
        ];
    }
}
