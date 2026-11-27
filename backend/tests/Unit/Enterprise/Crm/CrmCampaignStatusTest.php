<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\Status\CrmCampaignStatus;
use Tests\TestCase;

class CrmCampaignStatusTest extends TestCase
{
    public function test_every_status_is_exactly_one_of_editable_withdrawable_or_launched(): void
    {
        $groups = [CrmCampaignStatus::editable(), CrmCampaignStatus::withdrawable(), CrmCampaignStatus::launched()];
        $grouped = array_merge(...$groups);

        $this->assertSameSize(CrmCampaignStatus::cases(), $grouped);
        $this->assertEqualsCanonicalizing(CrmCampaignStatus::valuesArray(), CrmCampaignStatus::valuesOf($grouped));
    }
}
