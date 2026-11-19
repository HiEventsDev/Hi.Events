<?php

namespace HiEvents\Models;

class CrmCampaign extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'segment_definition' => 'array',
            'early_access_product_ids' => 'array',
            'ai_review' => 'array',
        ];
    }
}
