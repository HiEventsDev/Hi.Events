<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class CrmCacheVersion
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function bump(int $organizerId): void
    {
        $this->cache->forever($this->versionKey($organizerId), (string) Str::ulid());
    }

    public function bumpAfterCommit(int $organizerId): void
    {
        $this->databaseManager->afterCommit(fn () => $this->bump($organizerId));
    }

    public function current(int $organizerId): string
    {
        return (string) $this->cache->rememberForever($this->versionKey($organizerId), static fn () => (string) Str::ulid());
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $compute
     * @return TValue
     */
    public function remember(int $organizerId, string $key, int $seconds, Closure $compute): mixed
    {
        return $this->cache->remember(
            'crm:'.$organizerId.':v'.$this->current($organizerId).':'.$key,
            $seconds,
            $compute,
        );
    }

    private function versionKey(int $organizerId): string
    {
        return 'crm:'.$organizerId.':version';
    }
}
