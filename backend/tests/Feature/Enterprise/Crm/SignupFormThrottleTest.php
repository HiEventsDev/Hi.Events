<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SignupFormThrottleTest extends TestCase
{
    private const FIRST_FORM = 'sf_aaaaaaaaaaaaaaaa';

    private const SECOND_FORM = 'sf_bbbbbbbbbbbbbbbb';

    public function test_people_sharing_a_connection_have_a_separate_allowance_for_each_form(): void
    {
        $first = $this->limitFor(self::FIRST_FORM, '203.0.113.7');
        $second = $this->limitFor(self::SECOND_FORM, '203.0.113.7');
        $firstFromAnotherConnection = $this->limitFor(self::FIRST_FORM, '203.0.113.8');

        $this->assertNotSame($first->key, $second->key);
        $this->assertNotSame($first->key, $firstFromAnotherConnection->key);
        $this->assertSame($first->key, $this->limitFor(self::FIRST_FORM, '203.0.113.7')->key);
    }

    public function test_a_connection_can_submit_a_form_sixty_times_a_minute(): void
    {
        $limit = $this->limitFor(self::FIRST_FORM, '203.0.113.7');

        $this->assertSame(60, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }

    public function test_using_up_one_forms_allowance_leaves_another_form_open_to_the_same_connection(): void
    {
        foreach (range(1, 60) as $attempt) {
            $this->submitFrom(self::FIRST_FORM)->assertNotFound();
        }

        $this->submitFrom(self::FIRST_FORM)->assertTooManyRequests();
        $this->submitFrom(self::SECOND_FORM)->assertNotFound();
    }

    private function submitFrom(string $publicId): TestResponse
    {
        return $this->json('POST', "/public/crm/forms/$publicId/submissions", ['email' => 'fan@example.com']);
    }

    private function limitFor(string $publicId, string $ip): Limit
    {
        $request = Request::create("/public/crm/forms/$publicId/submissions", 'POST', server: ['REMOTE_ADDR' => $ip]);
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);

        $this->assertContains(ThrottleRequests::class.':crm-form-submit', Route::gatherRouteMiddleware($route));

        return RateLimiter::limiter('crm-form-submit')($request);
    }
}
