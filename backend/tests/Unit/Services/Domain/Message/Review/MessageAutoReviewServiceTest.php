<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Message\Review;

use Carbon\Carbon;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\Status\MessageStatus;
use HiEvents\Jobs\Event\SendMessagesJob;
use HiEvents\Jobs\Message\MessagePendingReviewJob;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\MessageRepositoryInterface;
use HiEvents\Services\Domain\Message\MessageApprovalService;
use HiEvents\Services\Domain\Message\MessageDispatchService;
use HiEvents\Services\Domain\Message\Review\DTO\MessageAiReviewResultDTO;
use HiEvents\Services\Domain\Message\Review\MessageAiReviewService;
use HiEvents\Services\Domain\Message\Review\MessageAutoReviewService;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageAutoReviewServiceTest extends TestCase
{
    private const PENDING_REVIEW = ['id' => 1, 'status' => 'PENDING_REVIEW'];

    private MessageRepositoryInterface|MockInterface $messageRepository;

    private MessageAiReviewService|MockInterface $aiReviewService;

    private MessageAutoReviewService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->messageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->aiReviewService = Mockery::mock(MessageAiReviewService::class);

        $this->service = new MessageAutoReviewService(
            $this->messageRepository,
            $this->aiReviewService,
            new MessageApprovalService(
                $this->messageRepository,
                new MessageDispatchService(
                    $this->messageRepository,
                    Mockery::mock(EventOccurrenceRepositoryInterface::class),
                ),
            ),
        );
    }

    public function test_a_confident_approval_sends_the_message(): void
    {
        $this->heldMessageIs($this->heldMessage());
        $this->aiVerdictIs(new MessageAiReviewResultDTO(true, 0.95, ['Event reminder']));
        $this->expectVerdictRecorded('APPROVED', 1);
        $this->messageRepository->shouldReceive('updateWhere')
            ->once()
            ->with(['status' => MessageStatus::PROCESSING->name], self::PENDING_REVIEW)
            ->andReturn(1);

        $this->service->review(1, 40);

        Bus::assertDispatched(SendMessagesJob::class, fn (SendMessagesJob $job) => $job->messageData->id === 1);
        Bus::assertNotDispatched(MessagePendingReviewJob::class);
    }

    public function test_a_confident_approval_of_a_future_message_schedules_it(): void
    {
        $this->heldMessageIs($this->heldMessage(Carbon::now()->addDay()->toDateTimeString()));
        $this->aiVerdictIs(new MessageAiReviewResultDTO(true, 0.95, []));
        $this->expectVerdictRecorded('APPROVED', 1);
        $this->messageRepository->shouldReceive('updateWhere')
            ->once()
            ->with(['status' => MessageStatus::SCHEDULED->name], self::PENDING_REVIEW)
            ->andReturn(1);

        $this->service->review(1, 40);

        Bus::assertNothingDispatched();
    }

    public function test_a_message_the_check_does_not_approve_goes_to_a_person_and_keeps_its_status(): void
    {
        $this->heldMessageIs($this->heldMessage());
        $this->aiVerdictIs(new MessageAiReviewResultDTO(false, 0.9, ['Links to an unrelated shop']));
        $this->expectVerdictRecorded('NEEDS_PERSON', 1);

        $this->service->review(1, 40);

        Bus::assertDispatched(MessagePendingReviewJob::class);
        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    public function test_a_retry_after_a_recorded_hand_off_notifies_a_person_without_asking_again(): void
    {
        $this->heldMessageIs($this->heldMessage()->setAiReview(['decision' => 'NEEDS_PERSON', 'confidence' => 0.4, 'reasons' => []]));
        $this->aiReviewService->shouldNotReceive('review');
        $this->messageRepository->shouldNotReceive('updateWhere');

        $this->service->review(1, 40);

        Bus::assertDispatched(MessagePendingReviewJob::class);
        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    public function test_a_retry_after_a_recorded_approval_sends_without_asking_again(): void
    {
        $this->heldMessageIs($this->heldMessage()->setAiReview(['decision' => 'APPROVED', 'confidence' => 0.95, 'reasons' => []]));
        $this->aiReviewService->shouldNotReceive('review');
        $this->messageRepository->shouldReceive('updateWhere')
            ->once()
            ->with(['status' => MessageStatus::PROCESSING->name], self::PENDING_REVIEW)
            ->andReturn(1);

        $this->service->review(1, 40);

        Bus::assertDispatched(SendMessagesJob::class);
    }

    public function test_a_message_no_longer_in_review_is_left_alone(): void
    {
        $this->heldMessageIs(null);
        $this->aiReviewService->shouldNotReceive('review');
        $this->messageRepository->shouldNotReceive('updateWhere');

        $this->service->review(1, 40);

        Bus::assertNothingDispatched();
    }

    public function test_a_message_decided_while_the_check_ran_is_left_alone(): void
    {
        $this->heldMessageIs($this->heldMessage());
        $this->aiVerdictIs(new MessageAiReviewResultDTO(true, 0.95, []));
        $this->expectVerdictRecorded('APPROVED', 0);

        $this->service->review(1, 40);

        Bus::assertNothingDispatched();
    }

    public function test_a_check_that_could_not_run_hands_the_message_to_a_person(): void
    {
        $this->heldMessageIs($this->heldMessage());
        $this->expectVerdictRecorded('NEEDS_PERSON', 1);

        $this->service->handOverUnreviewed(1);

        Bus::assertDispatched(MessagePendingReviewJob::class);
        Bus::assertNotDispatched(SendMessagesJob::class);
    }

    public function test_a_message_already_handed_to_a_person_is_not_handed_over_twice(): void
    {
        $this->heldMessageIs($this->heldMessage()->setAiReview(['decision' => 'NEEDS_PERSON']));
        $this->messageRepository->shouldNotReceive('updateWhere');

        $this->service->handOverUnreviewed(1);

        Bus::assertNothingDispatched();
    }

    private function heldMessageIs(?MessageDomainObject $message): void
    {
        $this->messageRepository->shouldReceive('findFirstWhere')->with(self::PENDING_REVIEW)->andReturn($message);
    }

    private function aiVerdictIs(MessageAiReviewResultDTO $result): void
    {
        $this->aiReviewService->shouldReceive('review')->once()->andReturn($result);
    }

    private function expectVerdictRecorded(string $decision, int $rowsUpdated): void
    {
        $this->messageRepository->shouldReceive('updateWhere')
            ->once()
            ->withArgs(fn (array $attributes, array $where) => $where === self::PENDING_REVIEW
                && json_decode($attributes['ai_review'] ?? '', true)['decision'] === $decision)
            ->andReturn($rowsUpdated);
    }

    private function heldMessage(?string $scheduledAt = null): MessageDomainObject
    {
        return (new MessageDomainObject)
            ->setId(1)
            ->setEventId(10)
            ->setSubject('Subject')
            ->setMessage('Body')
            ->setType('ALL_ATTENDEES')
            ->setSentByUserId(5)
            ->setStatus(MessageStatus::PENDING_REVIEW->name)
            ->setScheduledAt($scheduledAt)
            ->setEligibilityFailures(['event_too_new'])
            ->setSendData(['account_id' => 99, 'is_test' => false, 'send_copy_to_current_user' => false]);
    }
}
