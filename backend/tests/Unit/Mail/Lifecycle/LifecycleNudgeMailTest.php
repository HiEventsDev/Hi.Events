<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Lifecycle;

use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\Mail\Lifecycle\LifecycleNudgeMail;
use Tests\TestCase;

class LifecycleNudgeMailTest extends TestCase
{
    private function mail(string $body = '<p>Hi Sam,</p>'): LifecycleNudgeMail
    {
        return new LifecycleNudgeMail(
            nudgeType: LifecycleNudgeType::STRIPE_ONBOARDING_INCOMPLETE,
            renderedSubject: 'Subject',
            renderedBody: $body,
            ctaLabel: 'Connect Stripe',
            ctaUrl: 'https://app.example.com/stripe?a=1&b=2',
            unsubscribeUrl: 'https://app.example.com/emails/unsubscribe/token',
        );
    }

    public function test_reply_to_is_set_only_when_configured(): void
    {
        config(['mail.lifecycle.reply_to.address' => null]);
        $this->assertSame([], $this->mail()->envelope()->replyTo);

        config([
            'mail.lifecycle.reply_to.address' => 'dave@hi.events',
            'mail.lifecycle.reply_to.name' => 'Dave',
        ]);
        $replyTo = $this->mail()->envelope()->replyTo;

        $this->assertCount(1, $replyTo);
        $this->assertSame('dave@hi.events', $replyTo[0]->address);
        $this->assertSame('Dave', $replyTo[0]->name);
    }

    public function test_html_renders_link_signature_and_unsubscribe_without_mail_layout(): void
    {
        config([
            'mail.lifecycle.signature.name' => 'Dave',
            'mail.lifecycle.signature.title' => 'Founder, Hi.Events',
        ]);

        $html = $this->mail()->render();

        $this->assertStringContainsString('<a href="https://app.example.com/stripe?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('Dave<br>', $html);
        $this->assertStringContainsString('Founder, Hi.Events', $html);
        $this->assertStringContainsString('https://app.example.com/emails/unsubscribe/token', $html);
        $this->assertStringNotContainsString('class="button', $html);
        $this->assertStringNotContainsString('&lt;/td&gt;', $html);
    }

    public function test_signature_title_is_omitted_when_not_configured(): void
    {
        config([
            'mail.lifecycle.signature.name' => 'Dave',
            'mail.lifecycle.signature.title' => null,
        ]);

        $this->assertStringNotContainsString('Dave<br>', $this->mail()->render());
    }

    public function test_text_version_is_readable_plain_text(): void
    {
        config([
            'mail.lifecycle.signature.name' => 'Dave',
            'mail.lifecycle.signature.title' => null,
        ]);

        $mail = $this->mail("<p>Hi Sam,</p>\n<p>Fish &amp; Chips is live:</p>\n<ol>\n<li>Share it</li>\n<li>Embed it</li>\n</ol>");
        $text = view($mail->content()->text, $mail->content()->with)->render();

        $this->assertStringContainsString("Hi Sam,\n\nFish & Chips is live:\n\n- Share it\n- Embed it\n\nConnect Stripe: https://app.example.com/stripe?a=1&b=2\n\nDave\n", $text);
        $this->assertStringNotContainsString('<', $text);
    }
}
