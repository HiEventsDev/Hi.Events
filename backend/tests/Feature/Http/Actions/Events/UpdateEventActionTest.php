<?php

namespace Tests\Feature\Http\Actions\Events;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UpdateEventActionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private string $authToken;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $user = User::factory()->withAccount()->create();
        $this->user = $user;
        $this->accountId = $user->accounts()->first()->id;
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser($user);

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Update Test Organizer',
            'email' => 'organizer-'.uniqid().'@test.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Original Title',
            'description' => '<p>Original description</p>',
            'category' => 'MUSIC',
            'account_id' => $this->accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'status' => 'DRAFT',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'type' => 'SINGLE',
            'short_id' => 'ev_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_update_without_category_succeeds_and_preserves_it(): void
    {
        $response = $this->putJson(
            "/events/{$this->eventId}",
            [
                'title' => 'Updated Title',
                'start_date' => '2026-09-07 19:00:00',
            ],
            $this->authHeaders(),
        );

        $response->assertStatus(200);

        $event = DB::table('events')->where('id', $this->eventId)->first();
        $this->assertSame('Updated Title', $event->title);
        $this->assertSame('MUSIC', $event->category);
        $this->assertSame('<p>Original description</p>', $event->description);
    }

    public function test_update_with_explicit_null_description_clears_it(): void
    {
        $response = $this->putJson(
            "/events/{$this->eventId}",
            [
                'title' => 'Updated Title',
                'start_date' => '2026-09-07 19:00:00',
                'description' => null,
            ],
            $this->authHeaders(),
        );

        $response->assertStatus(200);

        $this->assertNull(DB::table('events')->where('id', $this->eventId)->value('description'));
    }

    private function authHeaders(): array
    {
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
