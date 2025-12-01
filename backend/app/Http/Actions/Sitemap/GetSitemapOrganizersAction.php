<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Sitemap;

use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Sitemap\GetSitemapOrganizersHandler;
use Illuminate\Http\Response;

class GetSitemapOrganizersAction extends BaseAction
{
    private const CONTENT_TYPE_XML = 'application/xml';

    public function __construct(
        private readonly GetSitemapOrganizersHandler $handler,
    ) {
    }

    public function __invoke(int $page): Response
    {
        try {
            $xml = $this->handler->handle($page);
            $cacheTtl = (int) config('sitemap.cache_ttl');

            return $this->xmlResponse(
                xmlContent: $xml,
                headers: [
                    'Content-Type' => self::CONTENT_TYPE_XML,
                    'Cache-Control' => "public, max-age=$cacheTtl",
                ]
            );
        } catch (ResourceNotFoundException) {
            return $this->notFoundResponse();
        }
    }
}
