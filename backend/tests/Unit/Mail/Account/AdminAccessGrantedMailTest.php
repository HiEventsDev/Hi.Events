<?php

namespace Tests\Unit\Mail\Account;

use Closure;
use HiEvents\Mail\Account\AdminAccessGrantedMail;
use ReflectionClass;
use Tests\TestCase;

class AdminAccessGrantedMailTest extends TestCase
{
    public function test_an_active_admin_is_described_as_having_access(): void
    {
        $html = (new AdminAccessGrantedMail(
            newAdminEmail: 'new-admin@example.com',
            grantedByEmail: 'colleague@example.com',
        ))->render();

        $this->assertStringContainsString('new-admin@example.com now has admin access', $html);
        $this->assertStringContainsString('remove their access', $html);
        $this->assertStringNotContainsString('was invited', $html);
    }

    public function test_a_pending_invitation_is_described_as_an_invitation(): void
    {
        $html = (new AdminAccessGrantedMail(
            newAdminEmail: 'new-admin@example.com',
            grantedByEmail: 'colleague@example.com',
            isInvitation: true,
        ))->render();

        $this->assertStringContainsString('new-admin@example.com was invited as an admin', $html);
        $this->assertStringContainsString('They get access once they accept', $html);
        $this->assertStringContainsString('remove the invitation', $html);
        $this->assertStringNotContainsString('now has admin access', $html);
    }

    public function test_a_mail_queued_by_a_previous_release_renders_the_access_wording(): void
    {
        $mail = (new ReflectionClass(AdminAccessGrantedMail::class))->newInstanceWithoutConstructor();
        Closure::bind(function (): void {
            $this->newAdminEmail = 'new-admin@example.com';
            $this->grantedByEmail = null;
        }, $mail, AdminAccessGrantedMail::class)();

        $this->assertFalse($mail->content()->with['isInvitation']);
    }
}
