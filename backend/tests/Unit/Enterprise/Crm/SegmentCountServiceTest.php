<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentCountService;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentQueryCompiler;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Tests\TestCase;

class SegmentCountServiceTest extends TestCase
{
    public function test_a_textable_count_read_back_from_a_cache_that_stores_numbers_as_text_is_still_a_number(): void
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('rememberForever')->andReturn('version');
        $cache->shouldReceive('remember')->once()->andReturn('3');
        $contacts = Mockery::mock(CrmContactRepositoryInterface::class);
        $contacts->shouldNotReceive('countTextable');

        $service = new SegmentCountService(
            $contacts,
            Mockery::mock(SegmentQueryCompiler::class),
            new CrmCacheVersion($cache, Mockery::mock(DatabaseManager::class)),
        );

        $this->assertSame(3, $service->countTextableIn(1, null, ['IE', 'US']));
    }
}
