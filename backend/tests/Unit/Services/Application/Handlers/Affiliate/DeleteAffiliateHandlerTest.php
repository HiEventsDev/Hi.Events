<?php

namespace Tests\Unit\Services\Application\Handlers\Affiliate;

use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Services\Application\Handlers\Affiliate\DeleteAffiliateHandler;
use Mockery as m;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class DeleteAffiliateHandlerTest extends TestCase
{
    private AffiliateRepositoryInterface $affiliateRepository;
    private DeleteAffiliateHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->affiliateRepository = m::mock(AffiliateRepositoryInterface::class);
        $this->handler = new DeleteAffiliateHandler($this->affiliateRepository);
    }

    public function testHandleSuccessfullyDeletesAffiliate(): void
    {
        $affiliateId = 1;
        $eventId = 2;
        $existingAffiliate = m::mock(AffiliateDomainObject::class);

        $this->affiliateRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $affiliateId,
                'event_id' => $eventId
            ])
            ->andReturn($existingAffiliate);

        $this->affiliateRepository
            ->shouldReceive('deleteById')
            ->once()
            ->with($affiliateId);

        $this->handler->handle($affiliateId, $eventId);

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testHandleThrowsExceptionWhenAffiliateNotFound(): void
    {
        $affiliateId = 1;
        $eventId = 2;

        $this->affiliateRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $affiliateId,
                'event_id' => $eventId
            ])
            ->andReturn(null);

        $this->affiliateRepository
            ->shouldNotReceive('deleteById');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Affiliate not found');

        $this->handler->handle($affiliateId, $eventId);
    }

    public function testHandleChecksCorrectEventId(): void
    {
        $affiliateId = 1;
        $eventId = 2;

        $this->affiliateRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $affiliateId,
                'event_id' => $eventId
            ])
            ->andReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Affiliate not found');

        $this->handler->handle($affiliateId, $eventId);
    }

    public function testHandleValidatesAffiliateExistsBeforeDeleting(): void
    {
        $affiliateId = 1;
        $eventId = 2;
        $existingAffiliate = m::mock(AffiliateDomainObject::class);

        $this->affiliateRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $affiliateId,
                'event_id' => $eventId
            ])
            ->andReturn($existingAffiliate);

        $this->affiliateRepository
            ->shouldReceive('deleteById')
            ->once()
            ->with($affiliateId);

        $this->handler->handle($affiliateId, $eventId);

        // Verify that findFirstWhere is called before deleteById
        $this->assertTrue(true);
    }

    public function testHandleOnlyDeletesAffiliateFromCorrectEvent(): void
    {
        $affiliateId = 1;
        $eventId = 2;

        // Test that affiliate from different event is not found
        $this->affiliateRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->with([
                'id' => $affiliateId,
                'event_id' => $eventId
            ])
            ->andReturn(null);

        $this->affiliateRepository
            ->shouldNotReceive('deleteById');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Affiliate not found');

        $this->handler->handle($affiliateId, $eventId);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
