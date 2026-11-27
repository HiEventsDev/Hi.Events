<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Actions\Events;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionQuotaService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionStore;
use HiEvents\Services\Infrastructure\Ai\Agents\EventShareCaptionAgent;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class EventShareSuggestionsActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const array GENERATED = ['captions' => [
        ['tone' => 'FRIENDLY', 'text' => 'Come along to jazz night on Friday'],
        ['tone' => 'SHORT', 'text' => 'Jazz night, this Friday'],
    ]];

    private User $owner;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.anthropic.key' => 'test-key',
            'app.saas_mode_enabled' => true,
        ]);

        $cache = new Repository(new ArrayStore);
        $this->app->instance(EventShareSuggestionQuotaService::class, new EventShareSuggestionQuotaService(new RateLimiter($cache)));
        $this->app->instance(EventShareSuggestionStore::class, new EventShareSuggestionStore($cache));

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($this->accountId, $this->createOrganizer($this->accountId), 'Jazz Night');

        DB::table('accounts')->where('id', $this->accountId)->update([
            'account_verified_at' => now(),
            'is_manually_verified' => true,
        ]);
    }

    public function test_a_trusted_account_generates_captions_that_are_kept_for_the_event(): void
    {
        EventShareCaptionAgent::fake([self::GENERATED]);

        $this->suggestions($this->owner)
            ->assertOk()
            ->assertJsonPath('data.availability', 'AVAILABLE')
            ->assertJsonPath('data.captions', [])
            ->assertJsonPath('data.remaining_generations', EventShareSuggestionQuotaService::DAILY_LIMIT);

        $this->generate($this->owner)
            ->assertOk()
            ->assertJsonPath('data.captions', self::GENERATED['captions'])
            ->assertJsonPath('data.remaining_generations', EventShareSuggestionQuotaService::DAILY_LIMIT - 1);

        EventShareCaptionAgent::assertPrompted(static fn ($prompt) => $prompt->contains('<title>Jazz Night</title>'));

        $this->suggestions($this->owner)
            ->assertOk()
            ->assertJsonPath('data.captions', self::GENERATED['captions'])
            ->assertJsonPath('data.remaining_generations', EventShareSuggestionQuotaService::DAILY_LIMIT - 1);
    }

    public function test_an_install_without_an_anthropic_key_reports_suggestions_as_not_configured(): void
    {
        config(['ai.providers.anthropic.key' => null]);
        EventShareCaptionAgent::fake()->preventStrayPrompts();

        $this->suggestions($this->owner)->assertOk()->assertJsonPath('data.availability', 'NOT_CONFIGURED');
        $this->generate($this->owner)->assertStatus(409);
    }

    public function test_a_cloud_account_that_is_neither_verified_nor_connected_to_stripe_cannot_generate(): void
    {
        DB::table('accounts')->where('id', $this->accountId)->update(['is_manually_verified' => false]);
        EventShareCaptionAgent::fake()->preventStrayPrompts();

        $this->suggestions($this->owner)->assertOk()->assertJsonPath('data.availability', 'PAYMENTS_NOT_CONNECTED');
        $this->generate($this->owner)->assertStatus(409);
    }

    public function test_an_account_that_has_used_its_daily_allowance_is_refused(): void
    {
        EventShareCaptionAgent::fake()->preventStrayPrompts();
        $quota = $this->app->make(EventShareSuggestionQuotaService::class);

        for ($generation = 0; $generation < EventShareSuggestionQuotaService::DAILY_LIMIT; $generation++) {
            $quota->recordGeneration($this->accountId);
        }

        $this->suggestions($this->owner)->assertOk()->assertJsonPath('data.remaining_generations', 0);
        $this->generate($this->owner)->assertStatus(429);
    }

    public function test_a_provider_failure_is_reported_without_using_the_allowance(): void
    {
        EventShareCaptionAgent::fake([['captions' => []]]);

        $this->generate($this->owner)->assertStatus(503);

        $this->suggestions($this->owner)
            ->assertJsonPath('data.remaining_generations', EventShareSuggestionQuotaService::DAILY_LIMIT);
    }

    public function test_a_read_only_member_can_see_suggestions_but_not_generate_them(): void
    {
        EventShareCaptionAgent::fake()->preventStrayPrompts();
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->suggestions($viewer)->assertOk();
        $this->generate($viewer)->assertForbidden()->assertJsonPath('error_code', 'READ_ONLY_ROLE');
    }

    public function test_another_accounts_event_is_out_of_reach(): void
    {
        EventShareCaptionAgent::fake()->preventStrayPrompts();
        [$stranger, $strangerAccountId] = $this->createAccountOwner();

        $this->getJson($this->url(), $this->headersFor($stranger, $strangerAccountId))->assertForbidden();
        $this->postJson($this->url(), [], $this->headersFor($stranger, $strangerAccountId))->assertForbidden();
    }

    private function suggestions(User $user): TestResponse
    {
        return $this->getJson($this->url(), $this->headersFor($user, $this->accountId));
    }

    private function generate(User $user): TestResponse
    {
        return $this->postJson($this->url(), [], $this->headersFor($user, $this->accountId));
    }

    private function url(): string
    {
        return '/events/'.$this->eventId.'/share-suggestions';
    }
}
