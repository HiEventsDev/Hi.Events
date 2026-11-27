<?php

namespace Tests\Unit\Services\Domain\Attendee;

use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Attendee\AttendeeCredentialRotationService;
use Mockery;
use Tests\TestCase;

class AttendeeCredentialRotationServiceTest extends TestCase
{
    public function test_it_issues_a_new_ticket_link_and_qr_value_each_time(): void
    {
        $repository = Mockery::mock(AttendeeRepositoryInterface::class);
        $repository->shouldReceive('countWhere')->twice()->andReturn(0);
        $service = new AttendeeCredentialRotationService($repository);

        $first = $service->issueFor(10);
        $second = $service->issueFor(10);

        $this->assertStringStartsWith(IdHelper::ATTENDEE_PREFIX.'_', $first->short_id);
        $this->assertStringStartsWith(strtoupper(IdHelper::ATTENDEE_PREFIX).'-', $first->public_id);
        $this->assertNotSame($first->short_id, $second->short_id);
        $this->assertNotSame($first->public_id, $second->public_id);
    }

    public function test_it_retries_when_the_qr_value_is_already_used_in_the_event(): void
    {
        $checked = [];
        $repository = Mockery::mock(AttendeeRepositoryInterface::class);
        $repository->shouldReceive('countWhere')
            ->twice()
            ->andReturnUsing(function (array $where) use (&$checked) {
                $checked[] = $where;

                return count($checked) === 1 ? 1 : 0;
            });

        $credentials = (new AttendeeCredentialRotationService($repository))->issueFor(10);

        $this->assertSame(10, $checked[0]['event_id']);
        $this->assertNotSame($checked[0]['public_id'], $checked[1]['public_id']);
        $this->assertSame($checked[1]['public_id'], $credentials->public_id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
