<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\Services\Domain\Event\EventSpamCheckService;
use HiEvents\Services\Infrastructure\Ai\Agents\EventSpamDetectionAgent;
use Illuminate\Config\Repository;
use Tests\TestCase;

class EventSpamCheckServiceTest extends TestCase
{
    private EventSpamCheckService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EventSpamCheckService(new Repository([
            'app' => [
                'saas_mode_enabled' => true,
                'event_spam_check_enabled' => true,
                'event_spam_check_confidence_threshold' => 0.7,
            ],
            'ai' => [
                'providers' => ['anthropic' => ['key' => 'test-key']],
            ],
        ]));
    }

    public function test_flags_spam_above_confidence_threshold(): void
    {
        EventSpamDetectionAgent::fake([
            ['is_spam' => true, 'confidence' => 0.95, 'reasons' => ['Phishing attempt']],
        ]);

        $result = $this->service->checkContent('Free crypto giveaway', 'Send us your wallet keys');

        $this->assertTrue($result->isSpam);
        $this->assertSame(0.95, $result->confidence);
        $this->assertSame(['Phishing attempt'], $result->reasons);
        $this->assertSame(EventSpamDetectionAgent::MODEL, $result->model);
    }

    public function test_does_not_flag_spam_below_confidence_threshold(): void
    {
        EventSpamDetectionAgent::fake([
            ['is_spam' => true, 'confidence' => 0.5, 'reasons' => ['Possibly promotional']],
        ]);

        $result = $this->service->checkContent('Community meetup', 'Join us');

        $this->assertFalse($result->isSpam);
        $this->assertSame(0.5, $result->confidence);
    }

    public function test_does_not_flag_clean_content(): void
    {
        EventSpamDetectionAgent::fake([
            ['is_spam' => false, 'confidence' => 0.99, 'reasons' => []],
        ]);

        $result = $this->service->checkContent('Annual Charity Gala', 'An evening of music');

        $this->assertFalse($result->isSpam);
    }

    public function test_prompt_contains_content_with_html_stripped(): void
    {
        EventSpamDetectionAgent::fake([
            ['is_spam' => false, 'confidence' => 0.9, 'reasons' => []],
        ]);

        $this->service->checkContent('My Event', '<p>Hello <strong>world</strong></p>');

        EventSpamDetectionAgent::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, '<title>My Event</title>')
                && str_contains($prompt->prompt, 'Hello world')
                && ! str_contains($prompt->prompt, '<strong>');
        });
    }

    public function test_is_enabled_requires_flags_and_api_key(): void
    {
        $this->assertTrue($this->service->isEnabled());

        $disabledService = new EventSpamCheckService(new Repository([
            'app' => [
                'saas_mode_enabled' => true,
                'event_spam_check_enabled' => false,
            ],
            'ai' => [
                'providers' => ['anthropic' => ['key' => 'test-key']],
            ],
        ]));

        $this->assertFalse($disabledService->isEnabled());

        $selfHostedService = new EventSpamCheckService(new Repository([
            'app' => [
                'saas_mode_enabled' => false,
                'event_spam_check_enabled' => true,
            ],
            'ai' => [
                'providers' => ['anthropic' => ['key' => 'test-key']],
            ],
        ]));

        $this->assertFalse($selfHostedService->isEnabled());

        $keylessService = new EventSpamCheckService(new Repository([
            'app' => [
                'saas_mode_enabled' => true,
                'event_spam_check_enabled' => true,
            ],
        ]));

        $this->assertFalse($keylessService->isEnabled());
    }

    public function test_hash_content_is_deterministic_and_null_safe(): void
    {
        $this->assertSame(
            $this->service->hashContent('Title', 'Description'),
            $this->service->hashContent('Title', 'Description'),
        );

        $this->assertNotSame(
            $this->service->hashContent('Title', 'Description'),
            $this->service->hashContent('Title', 'Changed'),
        );

        $this->assertSame(
            $this->service->hashContent(null, null),
            $this->service->hashContent(null, null),
        );
    }
}
