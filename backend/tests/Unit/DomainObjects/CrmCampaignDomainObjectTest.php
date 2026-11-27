<?php

declare(strict_types=1);

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use Tests\TestCase;

class CrmCampaignDomainObjectTest extends TestCase
{
    public function test_a_campaign_that_has_not_given_early_access_holds_no_hidden_tickets(): void
    {
        $campaign = (new CrmCampaignDomainObject)->setEarlyAccessProductIds([3, 4])->setEarlyAccessHiddenProductIds([3]);

        $this->assertSame([], $campaign->earlyAccessHiddenProductIds());
    }

    public function test_a_campaign_holds_only_the_tickets_it_hid(): void
    {
        $campaign = (new CrmCampaignDomainObject)->setPromoCodeId(9)->setEarlyAccessProductIds([3, 4])->setEarlyAccessHiddenProductIds(['3']);

        $this->assertSame([3], $campaign->earlyAccessHiddenProductIds());
    }

    public function test_a_campaign_whose_early_access_ended_holds_nothing(): void
    {
        $campaign = (new CrmCampaignDomainObject)->setPromoCodeId(9)->setEarlyAccessProductIds([3, 4])->setEarlyAccessHiddenProductIds([]);

        $this->assertSame([], $campaign->earlyAccessHiddenProductIds());
    }

    public function test_a_campaign_launched_before_hidden_tickets_were_recorded_holds_every_early_access_ticket(): void
    {
        $campaign = (new CrmCampaignDomainObject)->setPromoCodeId(9)->setEarlyAccessProductIds([3, 4]);

        $this->assertSame([3, 4], $campaign->earlyAccessHiddenProductIds());
    }
}
