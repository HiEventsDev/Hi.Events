<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

class BoxOfficeReaderCache
{
    private const TTL_SECONDS = 30;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    /**
     * @param  Closure(): array  $resolve
     */
    public function remember(int $organizerId, Closure $resolve): array
    {
        return $this->cache->remember($this->key($organizerId), self::TTL_SECONDS, $resolve);
    }

    public function forget(int $organizerId): void
    {
        $this->cache->forget($this->key($organizerId));
    }

    private function key(int $organizerId): string
    {
        return 'box_office_readers:'.$organizerId;
    }
}
