<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\SignupForm\SignupSubmissionLimiter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository as CacheRepository;
use Tests\TestCase;

class SignupSubmissionLimiterTest extends TestCase
{
    private SignupSubmissionLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limiter = new SignupSubmissionLimiter(new RateLimiter(new CacheRepository(new ArrayStore)));
    }

    public function test_a_form_stops_sending_confirmation_emails_once_it_reaches_its_hourly_limit_and_resumes_an_hour_later(): void
    {
        foreach (range(1, SignupSubmissionLimiter::MAX_PER_FORM_PER_HOUR) as $number) {
            $this->assertTrue($this->limiter->attempt(1, 10, "fan$number@example.com"));
        }

        $this->assertFalse($this->limiter->attempt(1, 10, 'one.too.many@example.com'));
        $this->assertTrue($this->limiter->attempt(1, 11, 'one.too.many@example.com'));

        $this->travel(61)->minutes();
        $this->assertTrue($this->limiter->attempt(1, 10, 'later@example.com'));
    }

    public function test_an_organizer_emails_an_address_a_few_times_a_day_without_using_up_the_forms_limit(): void
    {
        foreach (range(1, SignupSubmissionLimiter::MAX_PER_ORGANIZER_PER_DAY) as $attempt) {
            $this->assertTrue($this->limiter->attempt(1, 10, 'eager@example.com'));
        }
        $this->assertFalse($this->limiter->attempt(1, 10, 'eager@example.com'));
        $this->assertFalse($this->limiter->attempt(1, 11, 'eager@example.com'));

        $this->travel(2)->hours();
        $this->assertFalse($this->limiter->attempt(1, 10, 'eager@example.com'));

        foreach (range(1, SignupSubmissionLimiter::MAX_PER_FORM_PER_HOUR) as $number) {
            $this->assertTrue($this->limiter->attempt(1, 10, "fan$number@example.com"));
        }
        $this->assertFalse($this->limiter->attempt(1, 10, 'one.too.many@example.com'));

        $this->travel(23)->hours();
        $this->assertTrue($this->limiter->attempt(1, 10, 'eager@example.com'));
    }

    public function test_someone_using_up_one_organizers_allowance_for_an_address_does_not_stop_other_organizers_emailing_it(): void
    {
        foreach (range(1, SignupSubmissionLimiter::MAX_PER_ORGANIZER_PER_DAY + 5) as $attempt) {
            $this->limiter->attempt(1, 10, 'target@example.com');
        }

        foreach (range(2, SignupSubmissionLimiter::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY) as $organizerId) {
            $this->assertTrue($this->limiter->attempt($organizerId, $organizerId * 10, 'target@example.com'));
        }
    }

    public function test_an_address_is_emailed_by_a_limited_number_of_organizers_a_day(): void
    {
        foreach (range(1, SignupSubmissionLimiter::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY) as $organizerId) {
            $this->assertTrue($this->limiter->attempt($organizerId, $organizerId * 10, 'target@example.com'));
        }

        $oneOrganizerTooMany = SignupSubmissionLimiter::MAX_ORGANIZERS_PER_ADDRESS_PER_DAY + 1;
        $this->assertFalse($this->limiter->attempt($oneOrganizerTooMany, 990, 'target@example.com'));
        $this->assertTrue($this->limiter->attempt(1, 10, 'target@example.com'));
        $this->assertTrue($this->limiter->attempt($oneOrganizerTooMany, 990, 'someone.else@example.com'));

        $this->travel(25)->hours();
        $this->assertTrue($this->limiter->attempt($oneOrganizerTooMany, 990, 'target@example.com'));
    }
}
