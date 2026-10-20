<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class FinancialQueryMetaTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($this->accountId, $this->createOrganizer($this->accountId));
    }

    public function test_members_without_financials_are_not_offered_money_sorts_or_filters(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($organizer, $this->accountId, ['VIEW_FINANCIALS' => false]);

        foreach ([$viewer, $organizer] as $member) {
            $orders = $this->getJson("/events/{$this->eventId}/orders", $this->headersFor($member, $this->accountId))
                ->assertOk()
                ->json('meta');

            $this->assertArrayNotHasKey('total_gross', $orders['allowed_sorts']);
            $this->assertArrayHasKey('created_at', $orders['allowed_sorts']);
            $this->assertNotContains('total_gross', $orders['allowed_filter_fields']);
            $this->assertContains('email', $orders['allowed_filter_fields']);
            $this->assertTrue(array_is_list($orders['allowed_filter_fields']));

            $affiliates = $this->getJson("/events/{$this->eventId}/affiliates", $this->headersFor($member, $this->accountId))
                ->assertOk()
                ->json('meta');

            $this->assertArrayNotHasKey('total_sales_gross', $affiliates['allowed_sorts']);
            $this->assertArrayHasKey('total_sales', $affiliates['allowed_sorts']);
        }
    }

    public function test_members_with_financials_keep_money_sorts_and_filters(): void
    {
        $viewerWithFinancials = $this->addAccountUser($this->accountId, Role::VIEWER);
        $this->setPermissionOverrides($viewerWithFinancials, $this->accountId, ['VIEW_FINANCIALS' => true]);

        foreach ([$this->owner, $viewerWithFinancials] as $member) {
            $orders = $this->getJson("/events/{$this->eventId}/orders", $this->headersFor($member, $this->accountId))
                ->assertOk()
                ->json('meta');

            $this->assertSame(['asc' => 'Amount Ascending', 'desc' => 'Amount Descending'], $orders['allowed_sorts']['total_gross']);
            $this->assertContains('total_gross', $orders['allowed_filter_fields']);

            $affiliates = $this->getJson("/events/{$this->eventId}/affiliates", $this->headersFor($member, $this->accountId))
                ->assertOk()
                ->json('meta');

            $this->assertArrayHasKey('total_sales_gross', $affiliates['allowed_sorts']);
        }
    }
}
