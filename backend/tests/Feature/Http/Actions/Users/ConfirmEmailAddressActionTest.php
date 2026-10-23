<?php

namespace Tests\Feature\Http\Actions\Users;

use HiEvents\Mail\Account\ConfirmEmailAddressEmail;
use HiEvents\Mail\User\ConfirmEmailChangeMail;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\User\EmailConfirmationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class ConfirmEmailAddressActionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_an_email_change_token_cannot_confirm_the_current_email_address(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->password('correct-horse-battery')->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $this->putJson('/users/me', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'attacker-'.uniqid().'@test.com',
            'timezone' => 'UTC',
            'locale' => 'en',
            'confirmation_password' => 'correct-horse-battery',
        ], $this->headersFor($user, $accountId))->assertOk();

        $changeToken = $this->sentToken(ConfirmEmailChangeMail::class);

        $this->postJson("/users/{$user->id}/confirm-email/{$changeToken}", [], $this->headersFor($user, $accountId))
            ->assertStatus(400);

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('email_verified_at'));
    }

    public function test_a_confirmation_token_confirms_the_address_it_was_sent_to(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;
        config(['app.enforce_email_confirmation_during_registration' => false]);

        app(EmailConfirmationService::class)->sendConfirmation(app(UserRepositoryInterface::class)->findById($user->id), $accountId);
        $token = $this->sentToken(ConfirmEmailAddressEmail::class);

        $this->postJson("/users/{$user->id}/confirm-email/{$token}", [], $this->headersFor($user, $accountId))
            ->assertSuccessful();

        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('email_verified_at'));
    }

    public function test_a_confirmation_token_is_rejected_once_the_email_address_has_changed(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;
        config(['app.enforce_email_confirmation_during_registration' => false]);

        app(EmailConfirmationService::class)->sendConfirmation(app(UserRepositoryInterface::class)->findById($user->id), $accountId);
        $token = $this->sentToken(ConfirmEmailAddressEmail::class);
        DB::table('users')->where('id', $user->id)->update(['email' => 'someone-else-'.uniqid().'@test.com']);

        $this->postJson("/users/{$user->id}/confirm-email/{$token}", [], $this->headersFor($user, $accountId))
            ->assertStatus(400);

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('email_verified_at'));
    }

    /**
     * @param  class-string<Mailable>  $mailable
     */
    private function sentToken(string $mailable): string
    {
        $sent = Mail::sent($mailable)->first() ?? Mail::queued($mailable)->first();
        $this->assertNotNull($sent, "$mailable was not sent");

        return (fn () => $this->token)->call($sent);
    }
}
