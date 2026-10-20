<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\LifecycleEmails;

use HiEvents\Models\User;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailUnsubscribeTokens;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UnsubscribeFromLifecycleEmailsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.saas_mode_enabled' => true]);
    }

    public function test_a_valid_token_unsubscribes_and_clears_the_marketing_opt_in(): void
    {
        $user = $this->optedInUser();

        $this->postJson($this->url($this->tokenFor($user)))->assertNoContent();

        $user->refresh();
        $this->assertNotNull($user->lifecycle_emails_unsubscribed_at);
        $this->assertNull($user->marketing_opted_in_at);
    }

    public function test_a_mail_client_one_click_post_unsubscribes(): void
    {
        $user = $this->optedInUser();

        $this->post($this->url($this->tokenFor($user)), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();

        $this->assertNotNull($user->fresh()->lifecycle_emails_unsubscribed_at);
    }

    public function test_unsubscribing_twice_is_harmless(): void
    {
        $user = $this->optedInUser();

        $this->postJson($this->url($this->tokenFor($user)))->assertNoContent();
        $this->postJson($this->url($this->tokenFor($user)))->assertNoContent();

        $this->assertNotNull($user->fresh()->lifecycle_emails_unsubscribed_at);
    }

    public function test_a_tampered_token_is_not_found_and_changes_nothing(): void
    {
        $user = $this->optedInUser();
        [, $signature] = explode('.', $this->tokenFor($user));

        $this->postJson($this->url(($user->id + 1).'.'.$signature))->assertNotFound();
        $this->postJson($this->url('not-a-token'))->assertNotFound();

        $this->assertNull($user->fresh()->lifecycle_emails_unsubscribed_at);
        $this->assertNotNull($user->fresh()->marketing_opted_in_at);
    }

    public function test_the_endpoint_does_not_exist_outside_saas_mode(): void
    {
        config(['app.saas_mode_enabled' => false]);
        $user = $this->optedInUser();

        $this->postJson($this->url($this->tokenFor($user)))->assertNotFound();

        $this->assertNull($user->fresh()->lifecycle_emails_unsubscribed_at);
    }

    private function url(string $token): string
    {
        return '/public/lifecycle-emails/unsubscribe/'.$token;
    }

    private function tokenFor(User $user): string
    {
        return $this->app->make(LifecycleEmailUnsubscribeTokens::class)->tokenFor($user->id);
    }

    private function optedInUser(): User
    {
        return User::forceCreate([
            'email' => 'owner@example.com',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'password' => 'hash',
            'timezone' => 'UTC',
            'locale' => 'en',
            'marketing_opted_in_at' => now(),
        ]);
    }
}
