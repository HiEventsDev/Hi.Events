<?php

return [
    'events_per_page' => env('SITEMAP_EVENTS_PER_PAGE', 1000),
    'organizers_per_page' => env('SITEMAP_ORGANIZERS_PER_PAGE', 1000),
    'cache_ttl' => env('SITEMAP_CACHE_TTL', 3600), // 1 hour
];
