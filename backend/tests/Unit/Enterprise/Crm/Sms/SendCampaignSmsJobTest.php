<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm\Sms;

use HiEvents\DomainObjects\Enums\CrmRecipientFailureReason;
use HiEvents\Enterprise\Crm\Exceptions\SmsProviderException;
use HiEvents\Enterprise\Crm\Jobs\SendCampaignSmsJob;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsCampaignDeliveryService;
use Illuminate\Contracts\Queue\Job;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SendCampaignSmsJobTest extends TestCase
{
    public static function attempts(): array
    {
        return [
            'first attempt' => [1, 30],
            'second attempt' => [2, 120],
            'third attempt' => [3, 300],
            'fourth attempt' => [4, 600],
            'every later attempt' => [9, 600],
        ];
    }

    #[DataProvider('attempts')]
    public function test_a_text_the_provider_could_not_take_yet_is_tried_again_later_without_failing_the_recipient(int $attempt, int $expectedDelay): void
    {
        $this->freezeSecond();
        $delivery = Mockery::mock(SmsCampaignDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->with(7)->andThrow(new SmsProviderException('Too many requests'));
        $delivery->shouldNotReceive('markFailed');
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn($attempt);
        $queueJob->shouldReceive('retryUntil')->andReturn(now()->addHour()->getTimestamp());
        $queueJob->shouldReceive('release')->once()->with($expectedDelay);

        (new SendCampaignSmsJob(7))->setJob($queueJob)->handle($delivery);
    }

    public function test_a_text_the_provider_never_took_within_the_retry_window_ends_failed_with_the_reason(): void
    {
        $this->freezeSecond();
        $delivery = Mockery::mock(SmsCampaignDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->with(7)->andThrow(new SmsProviderException('Too many requests'));
        $delivery->shouldReceive('markFailed')->once()->with(7, CrmRecipientFailureReason::PROVIDER_UNAVAILABLE);
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(12);
        $queueJob->shouldReceive('retryUntil')->andReturn(now()->addSeconds(600)->getTimestamp());
        $queueJob->shouldNotReceive('release');

        (new SendCampaignSmsJob(7))->setJob($queueJob)->handle($delivery);
    }

    public function test_an_error_that_is_not_the_text_provider_is_left_to_the_queue(): void
    {
        $delivery = Mockery::mock(SmsCampaignDeliveryService::class);
        $delivery->shouldReceive('deliver')->once()->andThrow(new RuntimeException('The database went away.'));
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldNotReceive('release');

        $this->expectException(RuntimeException::class);

        (new SendCampaignSmsJob(7))->setJob($queueJob)->handle($delivery);
    }

    public function test_the_provider_is_retried_for_two_hours_while_other_errors_stop_after_three(): void
    {
        $this->freezeSecond();
        $job = new SendCampaignSmsJob(7);

        $this->assertSame(now()->addHours(2)->getTimestamp(), $job->retryUntil()->getTimestamp());
        $this->assertSame(3, $job->maxExceptions);
    }
}
