<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use Tests\TestCase;

class ApiKeyAccessTest extends TestCase
{
    public function test_maps_access_levels_to_sanctum_abilities_and_back(): void
    {
        $this->assertSame(['read'], ApiKeyAccess::READ_ONLY->abilities());
        $this->assertSame(['read', 'write'], ApiKeyAccess::READ_WRITE->abilities());

        $this->assertSame(ApiKeyAccess::READ_ONLY, ApiKeyAccess::fromAbilities(['read']));
        $this->assertSame(ApiKeyAccess::READ_WRITE, ApiKeyAccess::fromAbilities(['read', 'write']));
        $this->assertSame(ApiKeyAccess::READ_ONLY, ApiKeyAccess::fromAbilities([]));
    }
}
