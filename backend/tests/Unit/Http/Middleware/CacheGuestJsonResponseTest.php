<?php

namespace Tests\Unit\Http\Middleware;

use HiEvents\Http\Middleware\CacheGuestJsonResponse;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CacheGuestJsonResponseTest extends TestCase
{
    private CacheGuestJsonResponse $middleware;

    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new CacheGuestJsonResponse(new CacheRepository(new ArrayStore));
    }

    public function test_a_guest_repeat_request_is_served_from_cache_with_the_same_body(): void
    {
        $first = $this->send('/api/public/organizers/1/events?page=1&eventsStatus=upcoming');
        $second = $this->send('/api/public/organizers/1/events?eventsStatus=upcoming&page=1');

        $this->assertSame(1, $this->calls);
        $this->assertSame(200, $second->getStatusCode());
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame('application/json', $second->headers->get('Content-Type'));
    }

    public function test_entries_are_separated_by_path_query_and_locale(): void
    {
        $this->send('/api/public/organizers/1/events?eventsStatus=upcoming');
        $this->send('/api/public/organizers/2/events?eventsStatus=upcoming');
        $this->send('/api/public/organizers/1/events?eventsStatus=ended');
        App::setLocale('de');
        $this->send('/api/public/organizers/1/events?eventsStatus=upcoming');

        $this->assertSame(4, $this->calls);
    }

    public function test_query_params_outside_the_cacheable_list_share_one_entry(): void
    {
        $first = $this->send('/api/public/organizers/1/events?eventsStatus=upcoming&utm_source=a');
        $second = $this->send('/api/public/organizers/1/events?eventsStatus=upcoming&cache_buster=123');

        $this->assertSame(1, $this->calls);
        $this->assertSame($first->getContent(), $second->getContent());
    }

    public function test_entries_expire_after_the_configured_ttl(): void
    {
        $this->send('/api/public/organizers/1/events?eventsStatus=upcoming');
        $this->travel(11)->seconds();
        $this->send('/api/public/organizers/1/events?eventsStatus=upcoming');

        $this->assertSame(2, $this->calls);
    }

    public function test_authenticated_requests_and_error_responses_are_never_cached(): void
    {
        $this->send('/api/public/organizers/1/events', 404);
        $this->send('/api/public/organizers/1/events', 404);

        Auth::shouldReceive('check')->andReturnTrue();
        $this->send('/api/public/organizers/2/events');
        $this->send('/api/public/organizers/2/events');

        $this->assertSame(4, $this->calls);
    }

    private function send(string $uri, int $status = 200): JsonResponse
    {
        return $this->middleware->handle(
            Request::create($uri),
            function () use ($status) {
                $this->calls++;

                return new JsonResponse(['data' => [], 'call' => $this->calls], $status);
            },
            10,
            'page',
            'eventsStatus',
        );
    }
}
