<?php

namespace Tests\Feature\Console;

use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSsoConnections;
use Tests\TestCase;

class SsoVerifyDomainCommandTest extends TestCase
{
    use CreatesSsoConnections;
    use DatabaseTransactions;

    public function test_it_verifies_a_domain_after_confirmation(): void
    {
        $accountId = $this->accountId();
        $domainId = $this->addDomain($accountId, 'acme.test', verified: false);

        $this->artisan('sso:verify-domain', ['domain' => 'ACME.test'])
            ->expectsConfirmation("Mark acme.test as verified for account {$accountId}?", 'yes')
            ->assertSuccessful();

        $this->assertNotNull(DB::table('account_sso_domains')->where('id', $domainId)->value('verified_at'));
    }

    public function test_it_moves_a_disputed_domain_to_the_chosen_account(): void
    {
        $holderId = $this->accountId();
        $claimantId = $this->accountId();
        $holderDomainId = $this->addDomain($holderId, 'acme.test');
        $claimantDomainId = $this->addDomain($claimantId, 'acme.test', verified: false);

        $this->createConnection($holderId, ['enforce_sso' => true]);

        $this->artisan('sso:verify-domain', ['domain' => 'acme.test', '--account' => $claimantId])
            ->expectsOutputToContain("Account {$holderId} requires single sign-on.")
            ->expectsConfirmation("acme.test is verified by account {$holderId}. Remove it from that account and verify it for account {$claimantId}?", 'yes')
            ->assertSuccessful();

        $this->assertNull(DB::table('account_sso_domains')->where('id', $holderDomainId)->value('verified_at'));
        $this->assertNotNull(DB::table('account_sso_domains')->where('id', $claimantDomainId)->value('verified_at'));
    }

    public function test_a_declined_move_changes_nothing(): void
    {
        $holderId = $this->accountId();
        $claimantId = $this->accountId();
        $holderDomainId = $this->addDomain($holderId, 'acme.test');
        $this->addDomain($claimantId, 'acme.test', verified: false);

        $this->artisan('sso:verify-domain', ['domain' => 'acme.test', '--account' => $claimantId])
            ->expectsConfirmation("acme.test is verified by account {$holderId}. Remove it from that account and verify it for account {$claimantId}?", 'no')
            ->assertFailed();

        $this->assertNotNull(DB::table('account_sso_domains')->where('id', $holderDomainId)->value('verified_at'));
    }

    private function accountId(): int
    {
        return User::factory()->withAccount()->create()->accounts()->first()->id;
    }
}
