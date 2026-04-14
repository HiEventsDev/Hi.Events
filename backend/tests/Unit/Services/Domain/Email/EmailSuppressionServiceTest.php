<?php

namespace Tests\Unit\Services\Domain\Email;

use HiEvents\DomainObjects\EmailSuppressionDomainObject;
use HiEvents\DomainObjects\Status\EmailSuppressionReasonEnum;
use HiEvents\Repository\Interfaces\EmailSuppressionRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailSuppressionService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class EmailSuppressionServiceTest extends TestCase
{
    private EmailSuppressionRepositoryInterface $repository;
    private EmailSuppressionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = m::mock(EmailSuppressionRepositoryInterface::class);
        $this->service = new EmailSuppressionService($this->repository);
    }

    public function testIsEmailSuppressedReturnsFalseWhenFeatureDisabled(): void
    {
        config(['services.ses.suppression_enabled' => false]);

        $result = $this->service->isEmailSuppressed('test@example.com', 1);

        $this->assertFalse($result);
    }

    public function testIsEmailSuppressedReturnsFalseWhenNoSuppressionsExist(): void
    {
        config(['services.ses.suppression_enabled' => true]);

        $this->repository->shouldReceive('findByEmail')
            ->once()
            ->with('test@example.com', 1)
            ->andReturn(new Collection([]));

        $result = $this->service->isEmailSuppressed('test@example.com', 1);

        $this->assertFalse($result);
    }

    public function testPermanentBounceSuppressesAllEmailTypes(): void
    {
        config(['services.ses.suppression_enabled' => true]);

        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $suppression->shouldReceive('getReason')->andReturn(EmailSuppressionReasonEnum::BOUNCE->value);
        $suppression->shouldReceive('getBounceType')->andReturn('Permanent');

        $this->repository->shouldReceive('findByEmail')
            ->andReturn(new Collection([$suppression]));

        $this->assertTrue($this->service->isEmailSuppressed('test@example.com', 1, 'marketing'));
        $this->assertTrue($this->service->isEmailSuppressed('test@example.com', 1, 'transactional'));
    }

    public function testTransientBounceSuppressesOnlyMarketing(): void
    {
        config(['services.ses.suppression_enabled' => true]);

        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $suppression->shouldReceive('getReason')->andReturn(EmailSuppressionReasonEnum::BOUNCE->value);
        $suppression->shouldReceive('getBounceType')->andReturn('Transient');

        $this->repository->shouldReceive('findByEmail')
            ->andReturn(new Collection([$suppression]));

        $this->assertTrue($this->service->isEmailSuppressed('test@example.com', 1, 'marketing'));
        $this->assertFalse($this->service->isEmailSuppressed('test@example.com', 1, 'transactional'));
    }

    public function testComplaintSuppressesOnlyMarketing(): void
    {
        config(['services.ses.suppression_enabled' => true]);

        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $suppression->shouldReceive('getReason')->andReturn(EmailSuppressionReasonEnum::COMPLAINT->value);

        $this->repository->shouldReceive('findByEmail')
            ->andReturn(new Collection([$suppression]));

        $this->assertTrue($this->service->isEmailSuppressed('test@example.com', 1, 'marketing'));
        $this->assertFalse($this->service->isEmailSuppressed('test@example.com', 1, 'transactional'));
    }

    public function testSuppressEmailUsesFirstOrCreate(): void
    {
        $suppression = m::mock(EmailSuppressionDomainObject::class);

        $this->repository->shouldReceive('findOrCreateSuppression')
            ->once()
            ->withArgs(function ($unique, $additional) {
                return $unique['email'] === 'test@example.com'
                    && $unique['reason'] === 'bounce'
                    && $additional['bounce_type'] === 'Permanent';
            })
            ->andReturn($suppression);

        $result = $this->service->suppressEmail(
            email: 'TEST@EXAMPLE.COM',
            reason: 'bounce',
            source: 'ses_notification',
            accountId: 1,
            bounceType: 'Permanent',
        );

        $this->assertSame($suppression, $result);
    }

    public function testSuppressEmailHandlesDuplicateGracefully(): void
    {
        $existingSuppression = m::mock(EmailSuppressionDomainObject::class);

        $this->repository->shouldReceive('findOrCreateSuppression')
            ->twice()
            ->andReturn($existingSuppression);

        $result1 = $this->service->suppressEmail(
            email: 'test@example.com',
            reason: 'bounce',
            source: 'ses_notification',
            bounceType: 'Permanent',
        );

        $result2 = $this->service->suppressEmail(
            email: 'test@example.com',
            reason: 'bounce',
            source: 'ses_notification',
            bounceType: 'Permanent',
        );

        $this->assertSame($existingSuppression, $result1);
        $this->assertSame($existingSuppression, $result2);
    }

    public function testRemoveSuppressionSoftDeletesMatchingRecords(): void
    {
        $suppression1 = m::mock(EmailSuppressionDomainObject::class);
        $suppression1->shouldReceive('getId')->andReturn(1);
        $suppression2 = m::mock(EmailSuppressionDomainObject::class);
        $suppression2->shouldReceive('getId')->andReturn(2);

        $this->repository->shouldReceive('findWhere')
            ->once()
            ->with(['email' => 'test@example.com', 'account_id' => 1])
            ->andReturn(new Collection([$suppression1, $suppression2]));

        $this->repository->shouldReceive('deleteById')
            ->once()
            ->with(1);
        $this->repository->shouldReceive('deleteById')
            ->once()
            ->with(2);

        $this->service->removeSuppression('test@example.com', 1);
    }

    public function testRemoveSuppressionFiltersByReason(): void
    {
        $suppression = m::mock(EmailSuppressionDomainObject::class);
        $suppression->shouldReceive('getId')->andReturn(1);

        $this->repository->shouldReceive('findWhere')
            ->once()
            ->with(['email' => 'test@example.com', 'reason' => 'bounce'])
            ->andReturn(new Collection([$suppression]));

        $this->repository->shouldReceive('deleteById')
            ->once()
            ->with(1);

        $this->service->removeSuppression('test@example.com', null, 'bounce');
    }
}
