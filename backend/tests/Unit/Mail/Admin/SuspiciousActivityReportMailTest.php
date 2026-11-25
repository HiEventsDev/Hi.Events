<?php

namespace Tests\Unit\Mail\Admin;

use Carbon\CarbonImmutable;
use HiEvents\Mail\Admin\SuspiciousActivityReportMail;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityAccountDTO;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityFindingDTO;
use HiEvents\Services\Domain\Abuse\DTO\SuspiciousActivityItemDTO;
use Tests\TestCase;

class SuspiciousActivityReportMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://app.example.com']);
    }

    public function test_subject_names_the_first_finding_and_counts_the_rest(): void
    {
        $this->assertSame(
            '[Action Required] Card testing (+1 more)',
            (new SuspiciousActivityReportMail([$this->finding('Card testing'), $this->finding('Signups')]))->envelope()->subject,
        );
        $this->assertSame(
            '[Action Required] Card testing',
            (new SuspiciousActivityReportMail([$this->finding('Card testing')]))->envelope()->subject,
        );
    }

    public function test_links_each_account_to_its_admin_page_and_suspend_action(): void
    {
        $html = (new SuspiciousActivityReportMail([
            $this->finding('Card testing', [$this->itemWithAccount(isSuspended: false)], 'failed-jobs', 'Open failed jobs'),
        ]))->render();

        $this->assertStringContainsString('href="https://app.example.com/admin/accounts/412"', $html);
        $this->assertStringContainsString('href="https://app.example.com/admin/accounts/412?action=suspend"', $html);
        $this->assertStringContainsString('href="https://app.example.com/admin/failed-jobs"', $html);
        $this->assertStringContainsString('owner@example.com', $html);
        $this->assertStringContainsString('Check the declines', $html);
    }

    public function test_does_not_offer_to_suspend_an_account_that_is_already_suspended(): void
    {
        $html = (new SuspiciousActivityReportMail([
            $this->finding('Card testing', [$this->itemWithAccount(isSuspended: true)]),
        ]))->render();

        $this->assertStringContainsString('already suspended', $html);
        $this->assertStringNotContainsString('action=suspend', $html);
    }

    public function test_links_signup_groups_to_an_account_search(): void
    {
        $html = (new SuspiciousActivityReportMail([
            $this->finding('Signups', [new SuspiciousActivityItemDTO(label: '390x "Crypto (help)"', accountSearch: 'Crypto (help)')]),
        ]))->render();

        $this->assertStringContainsString('href="https://app.example.com/admin/accounts?search=Crypto%20%28help%29"', $html);
    }

    public function test_user_supplied_names_cannot_become_links(): void
    {
        $html = (new SuspiciousActivityReportMail([
            $this->finding('Scam names', [
                new SuspiciousActivityItemDTO(label: 'Event 1: "[Verify your account](https://evil.example)"', accountId: 412),
            ]),
        ]))->render();

        $this->assertStringNotContainsString('href="https://evil.example"', $html);
        $this->assertStringContainsString('[Verify your account](https://evil.example)', $html);
    }

    private function itemWithAccount(bool $isSuspended): SuspiciousActivityItemDTO
    {
        return (new SuspiciousActivityItemDTO(
            label: 'Event 9031: "Free webinar"',
            facts: ['LIVE', '14 orders with failed payments'],
            accountId: 412,
        ))->withAccount(new SuspiciousActivityAccountDTO(
            id: 412,
            name: 'Fast [Help](https://evil.example)',
            email: 'owner@example.com',
            createdAt: CarbonImmutable::now()->subHours(3),
            isVerified: false,
            hasStripe: false,
            liveEvents: 1,
            isSuspended: $isSuspended,
        ));
    }

    private function finding(string $summary, array $items = [], ?string $adminPath = null, ?string $adminLinkLabel = null): SuspiciousActivityFindingDTO
    {
        return new SuspiciousActivityFindingDTO(
            type: 'failed_payment_burst',
            summary: $summary,
            items: $items,
            fingerprint: $summary,
            nextStep: 'Check the declines in the Stripe dashboard.',
            adminPath: $adminPath,
            adminLinkLabel: $adminLinkLabel,
        );
    }
}
