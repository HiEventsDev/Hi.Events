<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Message;

use HiEvents\Jobs\Message\ReviewMessageWithAiJob;
use HiEvents\Services\Domain\Message\Review\MessageAutoReviewService;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReviewMessageWithAiJobTest extends TestCase
{
    public function test_it_reviews_the_message(): void
    {
        $autoReviewService = Mockery::mock(MessageAutoReviewService::class);
        $autoReviewService->shouldReceive('review')->once()->with(7, 40);

        (new ReviewMessageWithAiJob(7, 40))->handle($autoReviewService);
    }

    public function test_a_failed_job_hands_the_message_to_a_person(): void
    {
        $autoReviewService = Mockery::mock(MessageAutoReviewService::class);
        $autoReviewService->shouldReceive('handOverUnreviewed')->once()->with(7);
        $this->app->instance(MessageAutoReviewService::class, $autoReviewService);

        (new ReviewMessageWithAiJob(7, 40))->failed(new RuntimeException('timeout'));
    }
}
