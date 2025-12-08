<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Sitemap;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Sitemap\SitemapGeneratorService;
use Illuminate\Support\Facades\Cache;

class GetSitemapEventsHandler
{
    private const CACHE_KEY_PREFIX = 'sitemap:events:';
    private const MIN_PAGE = 1;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly SitemapGeneratorService $sitemapGenerator,
    )
    {
    }

    public function handle(int $page): string
    {
        if ($page < self::MIN_PAGE) {
            throw new ResourceNotFoundException(__('Page must be a positive integer'));
        }

        $eventsPerPage = (int) config('sitemap.events_per_page');
        $totalEvents = $this->eventRepository->getSitemapEventCount();
        $totalPages = $this->calculateTotalPages($totalEvents, $eventsPerPage);

        if ($page > $totalPages) {
            throw new ResourceNotFoundException(__('Page not found'));
        }

        $cacheTtl = (int) config('sitemap.cache_ttl');
        $cacheKey = self::CACHE_KEY_PREFIX . $page;

        return Cache::remember($cacheKey, $cacheTtl, function () use ($page, $eventsPerPage): string {
            $events = $this->eventRepository->getSitemapEvents($page, $eventsPerPage);
            $baseUrl = rtrim((string) config('app.frontend_url'), '/');

            return $this->sitemapGenerator->generateEventsSitemap(
                $events->getCollection(),
                $baseUrl
            );
        });
    }

    private function calculateTotalPages(int $totalEvents, int $eventsPerPage): int
    {
        return max(self::MIN_PAGE, (int) ceil($totalEvents / $eventsPerPage));
    }
}
