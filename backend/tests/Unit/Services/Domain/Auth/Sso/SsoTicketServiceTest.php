<?php

namespace Tests\Unit\Services\Domain\Auth\Sso;

use HiEvents\DomainObjects\Enums\SsoErrorCode;
use HiEvents\DomainObjects\Enums\SsoTicketType;
use HiEvents\Exceptions\SsoException;
use HiEvents\Services\Domain\Auth\Sso\SsoTicketService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class SsoTicketServiceTest extends TestCase
{
    private SsoTicketService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SsoTicketService(new Repository(new ArrayStore));
    }

    public function test_a_state_is_consumed_once_by_the_browser_that_created_it(): void
    {
        $state = $this->service->issue(SsoTicketType::STATE, ['binding_hash' => SsoTicketService::hashBinding('binding'), 'state' => ['provider' => 'google']]);

        $this->assertSame('google', $this->service->consume(SsoTicketType::STATE, $state, 'binding')['state']['provider']);

        $this->assertErrorCode(SsoErrorCode::STATE_INVALID, fn () => $this->service->consume(SsoTicketType::STATE, $state, 'binding'));
    }

    public function test_another_browser_cannot_consume_a_state_or_burn_it(): void
    {
        $state = $this->service->issue(SsoTicketType::STATE, ['binding_hash' => SsoTicketService::hashBinding('binding')]);

        $this->assertErrorCode(SsoErrorCode::STATE_INVALID, fn () => $this->service->consume(SsoTicketType::STATE, $state, 'other-binding'));
        $this->assertSame(SsoTicketService::hashBinding('binding'), $this->service->consume(SsoTicketType::STATE, $state, 'binding')['binding_hash']);
    }

    public function test_tickets_check_the_binding_and_are_single_use(): void
    {
        $ticket = $this->service->issue(SsoTicketType::LOGIN, ['user_id' => 1, 'binding_hash' => SsoTicketService::hashBinding('binding')]);

        $this->assertErrorCode(SsoErrorCode::TICKET_INVALID, fn () => $this->service->find(SsoTicketType::LOGIN, $ticket, 'other-binding'));
        $this->assertSame(1, $this->service->consume(SsoTicketType::LOGIN, $ticket, 'binding')['user_id']);
        $this->assertErrorCode(SsoErrorCode::TICKET_INVALID, fn () => $this->service->consume(SsoTicketType::LOGIN, $ticket, 'binding'));
    }

    public function test_tickets_of_one_type_cannot_be_used_as_another(): void
    {
        $ticket = $this->service->issue(SsoTicketType::REAUTH, ['user_id' => 1]);

        $this->assertErrorCode(SsoErrorCode::TICKET_INVALID, fn () => $this->service->find(SsoTicketType::LOGIN, $ticket));
    }

    private function assertErrorCode(SsoErrorCode $expected, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected an SSO exception');
        } catch (SsoException $exception) {
            $this->assertSame($expected, $exception->errorCode);
        }
    }
}
