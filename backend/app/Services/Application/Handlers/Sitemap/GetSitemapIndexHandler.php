<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Sitemap;

use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Sitemap\SitemapGeneratorService;
use Illuminate\Support\Facades\Cache;

class GetSitemapIndexHandler
{
    private const CACHE_KEY = 'sitemap:index';
    private const MIN_PAGES = 1;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly SitemapGeneratorService $sitemapGenerator,
    ) {
    }

    public function handle(): string
    {
        $cacheTtl = (int) config('sitemap.cache_ttl');

        return Cache::remember(self::CACHE_KEY, $cacheTtl, function (): string {
            $eventsPerPage = (int) config('sitemap.events_per_page');
            $organizersPerPage = (int) config('sitemap.organizers_per_page');

            $totalEvents = $this->eventRepository->getSitemapEventCount();
            $totalOrganizers = $this->organizerRepository->getSitemapOrganizerCount();

            $totalEventPages = $this->calculateTotalPages($totalEvents, $eventsPerPage);
            $totalOrganizerPages = $this->calculateTotalPages($totalOrganizers, $organizersPerPage);

            $baseUrl = rtrim((string) config('app.frontend_url'), '/');
            $lastMod = now()->toAtomString();

            return $this->sitemapGenerator->generateSitemapIndex(
                $totalEventPages,
                $totalOrganizerPages,
                $baseUrl,
                $lastMod
            );
        });
    }

    private function calculateTotalPages(int $total, int $perPage): int
    {
        return max(self::MIN_PAGES, (int) ceil($total / $perPage));
    }
}
