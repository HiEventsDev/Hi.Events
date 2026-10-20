<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\TooManyPinAttemptsException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficePinAttemptLimiter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxOfficePinAttemptLimiterTest extends TestCase
{
    private MockInterface|RateLimiter $rateLimiter;

    private BoxOfficePinAttemptLimiter $limiter;

    private BoxOfficeDomainObject $boxOffice;

    private string $boxOfficeKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = Mockery::mock(RateLimiter::class);
        $this->limiter = new BoxOfficePinAttemptLimiter($this->rateLimiter);
        $this->boxOffice = (new BoxOfficeDomainObject)->setId(12)->setPinHash('first-hash');
        $this->boxOfficeKey = 'box_office_pin:12:'.substr(hash('sha256', 'first-hash'), 0, 16);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_a_device_over_its_attempt_allowance_is_locked_out(): void
    {
        $this->rateLimiter->shouldReceive('hit')->once()->with($this->boxOfficeKey.':10.0.0.1', 900)->andReturn(6);
        $this->rateLimiter->shouldNotReceive('hit')->with($this->boxOfficeKey, 900);
        $this->rateLimiter->shouldReceive('availableIn')->with($this->boxOfficeKey.':10.0.0.1')->andReturn(130);

        $this->expectException(TooManyPinAttemptsException::class);
        $this->expectExceptionMessage('3 minutes');

        $this->limiter->reserveAttempt($this->boxOffice, '10.0.0.1');
    }

    public function test_the_fifth_attempt_from_a_device_is_still_allowed(): void
    {
        $this->rateLimiter->shouldReceive('hit')->once()->with($this->boxOfficeKey.':10.0.0.1', 900)->andReturn(5);
        $this->rateLimiter->shouldReceive('hit')->once()->with($this->boxOfficeKey, 900)->andReturn(5);
        $this->rateLimiter->shouldNotReceive('availableIn');

        $this->limiter->reserveAttempt($this->boxOffice, '10.0.0.1');

        $this->assertTrue(true);
    }

    public function test_a_box_office_under_distributed_guessing_is_locked_for_everyone(): void
    {
        $this->rateLimiter->shouldReceive('hit')->once()->with($this->boxOfficeKey.':10.0.0.2', 900)->andReturn(1);
        $this->rateLimiter->shouldReceive('hit')->once()->with($this->boxOfficeKey, 900)->andReturn(21);
        $this->rateLimiter->shouldReceive('availableIn')->with($this->boxOfficeKey)->andReturn(600);

        $this->expectException(TooManyPinAttemptsException::class);
        $this->expectExceptionMessage('reset the PIN');

        $this->limiter->reserveAttempt($this->boxOffice, '10.0.0.2');
    }

    public function test_the_attempt_is_counted_before_the_pin_is_checked_so_parallel_guesses_cannot_overshoot(): void
    {
        $attempts = 0;
        $this->rateLimiter->shouldReceive('hit')->with($this->boxOfficeKey, 900)->andReturnUsing(function () use (&$attempts) {
            return ++$attempts;
        });
        $this->rateLimiter->shouldReceive('hit')->with(Mockery::pattern('/^'.preg_quote($this->boxOfficeKey, '/').':/'), 900)->andReturn(1);
        $this->rateLimiter->shouldReceive('availableIn')->andReturn(900);

        $allowed = 0;
        for ($i = 0; $i < 30; $i++) {
            try {
                $this->limiter->reserveAttempt($this->boxOffice, '10.0.1.'.$i);
                $allowed++;
            } catch (TooManyPinAttemptsException) {
            }
        }

        $this->assertSame(20, $allowed);
    }

    public function test_a_correct_pin_clears_the_device_but_only_refunds_its_own_box_office_attempt(): void
    {
        $this->rateLimiter->shouldReceive('clear')->once()->with($this->boxOfficeKey.':10.0.0.1');
        $this->rateLimiter->shouldReceive('decrement')->once()->with($this->boxOfficeKey, 900);
        $this->rateLimiter->shouldNotReceive('clear')->with($this->boxOfficeKey);

        $this->limiter->recordSuccess($this->boxOffice, '10.0.0.1');

        $this->assertTrue(true);
    }

    public function test_a_new_pin_starts_fresh_device_and_box_office_counters(): void
    {
        $rateLimiter = new RateLimiter(new Repository(new ArrayStore));
        $limiter = new BoxOfficePinAttemptLimiter($rateLimiter);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->reserveAttempt($this->boxOffice, '10.0.0.1');
        }

        try {
            $limiter->reserveAttempt($this->boxOffice, '10.0.0.1');
            $this->fail('Expected the sixth wrong PIN from the venue to be locked out');
        } catch (TooManyPinAttemptsException) {
        }

        $limiter->reserveAttempt((new BoxOfficeDomainObject)->setId(12)->setPinHash('reset-hash'), '10.0.0.1');

        $this->assertTrue(true);
    }
}
