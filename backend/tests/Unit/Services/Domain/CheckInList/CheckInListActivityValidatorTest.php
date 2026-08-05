<?php

namespace Tests\Unit\Services\Domain\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckInListActivityValidatorTest extends TestCase
{
    private CheckInListActivityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CheckInListActivityValidator;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function checkInList(?string $expiresAt, ?string $activatesAt): CheckInListDomainObject|MockInterface
    {
        $list = Mockery::mock(CheckInListDomainObject::class);
        $list->shouldReceive('getExpiresAt')->andReturn($expiresAt);
        $list->shouldReceive('getActivatesAt')->andReturn($activatesAt);

        return $list;
    }

    public function test_passes_when_no_window_set(): void
    {
        $this->validator->assertActive($this->checkInList(expiresAt: null, activatesAt: null));

        $this->assertTrue(true);
    }

    public function test_passes_when_within_window(): void
    {
        $this->validator->assertActive($this->checkInList(
            expiresAt: now()->addDay()->toDateTimeString(),
            activatesAt: now()->subDay()->toDateTimeString(),
        ));

        $this->assertTrue(true);
    }

    public function test_throws_when_expired(): void
    {
        $this->expectException(CannotCheckInException::class);
        $this->expectExceptionMessage('Check-in list has expired');

        $this->validator->assertActive($this->checkInList(
            expiresAt: now()->subMinute()->toDateTimeString(),
            activatesAt: null,
        ));
    }

    public function test_throws_when_not_yet_active(): void
    {
        $this->expectException(CannotCheckInException::class);
        $this->expectExceptionMessage('Check-in list is not active yet');

        $this->validator->assertActive($this->checkInList(
            expiresAt: null,
            activatesAt: now()->addMinute()->toDateTimeString(),
        ));
    }
}
