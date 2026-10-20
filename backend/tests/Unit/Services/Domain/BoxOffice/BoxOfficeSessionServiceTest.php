<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Enterprise\BoxOffice\Services\Domain\DTO\BoxOfficeSessionDTO;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class BoxOfficeSessionServiceTest extends TestCase
{
    private BoxOfficeSessionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BoxOfficeSessionService(new Repository(new ArrayStore));
    }

    private function boxOffice(): BoxOfficeDomainObject
    {
        return (new BoxOfficeDomainObject)->setId(12)->setEventId(7)->setPinHash('hash');
    }

    public function test_create_returns_a_resolvable_token(): void
    {
        $created = $this->service->create($this->boxOffice(), 'Sam', 44, 9);

        $this->assertStringStartsWith('bos_', $created->token);

        $resolved = $this->service->resolve($created->token);

        $this->assertNotNull($resolved);
        $this->assertSame(12, $resolved->box_office_id);
        $this->assertSame(7, $resolved->event_id);
        $this->assertSame('Sam', $resolved->operator_name);
        $this->assertSame(44, $resolved->event_occurrence_id);
        $this->assertSame(9, $resolved->stripe_terminal_reader_id);
        $this->assertSame('hash', $resolved->pin_hash);
    }

    public function test_an_organizer_session_remembers_the_pin_it_started_under(): void
    {
        $created = $this->service->create($this->boxOffice(), 'Sam', 44, null, authenticatedUserId: 21, authenticatedAccountId: 3);

        $resolved = $this->service->resolve($created->token);

        $this->assertSame(21, $resolved->authenticated_user_id);
        $this->assertSame('hash', $resolved->pin_hash);
    }

    public function test_update_replaces_the_stored_session_for_the_same_token(): void
    {
        $created = $this->service->create($this->boxOffice(), 'Sam', 44, 9);
        $updated = new BoxOfficeSessionDTO(
            box_office_id: 12,
            event_id: 7,
            operator_name: 'Sam',
            event_occurrence_id: 45,
            stripe_terminal_reader_id: 9,
            pin_hash: 'hash',
            expires_at: $created->session->expires_at,
        );

        $this->service->update($created->token, $updated);

        $resolved = $this->service->resolve($created->token);

        $this->assertSame(45, $resolved->event_occurrence_id);
        $this->assertSame(9, $resolved->stripe_terminal_reader_id);
        $this->assertSame($created->session->expires_at, $resolved->expires_at);
    }

    public function test_resolve_rejects_unknown_or_malformed_tokens(): void
    {
        $this->assertNull($this->service->resolve(null));
        $this->assertNull($this->service->resolve('not-a-token'));
        $this->assertNull($this->service->resolve('bos_'.str_repeat('x', 48)));
    }

    public function test_a_session_is_read_from_the_cache_once_per_request(): void
    {
        $store = new ArrayStore;
        $cache = new class($store) extends Repository
        {
            public int $reads = 0;

            public function get($key, $default = null): mixed
            {
                $this->reads++;

                return parent::get($key, $default);
            }
        };
        $service = new BoxOfficeSessionService($cache);
        $token = $service->create($this->boxOffice(), 'Sam', 44, 9)->token;

        $service->resolve($token);
        $service->resolve($token);

        $this->assertSame(1, $cache->reads);
    }

    public function test_forgetting_a_session_is_seen_by_the_next_resolve_in_the_same_request(): void
    {
        $created = $this->service->create($this->boxOffice(), 'Sam', 44, 9);
        $this->assertNotNull($this->service->resolve($created->token));

        $this->service->forget($created->token);

        $this->assertNull($this->service->resolve($created->token));
    }
}
