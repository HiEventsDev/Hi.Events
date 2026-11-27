<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\Enums\CrmAutomationTrigger;
use HiEvents\Enterprise\Crm\Services\Domain\Automation\AutomationDefaults;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenCatalog;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignTokenRendererTest extends TestCase
{
    private const VALUES = [
        'event_title' => 'Rock & <b>Roll</b> "Live"',
        'event_date' => 'June 5, 2027',
        'organizer_name' => "Jazz & Blues <script>alert('x')</script>",
    ];

    public function test_the_subject_gets_raw_values_and_is_trimmed(): void
    {
        $subject = (new CampaignTokenRenderer)->renderSubject('  {{ event_title }} on {{event_date}} by {{  organizer_name  }} ', self::VALUES);

        $this->assertSame(
            self::VALUES['event_title'].' on June 5, 2027 by '.self::VALUES['organizer_name'],
            $subject,
        );
    }

    public function test_the_body_gets_html_escaped_values(): void
    {
        $body = (new CampaignTokenRenderer)->renderBody('<p>{{ event_title }} / {{ organizer_name }}</p>', self::VALUES);

        $this->assertSame('<p>'.e(self::VALUES['event_title']).' / '.e(self::VALUES['organizer_name']).'</p>', $body);
        $this->assertStringNotContainsString('<b>', $body);
        $this->assertStringNotContainsString('<script', $body);
    }

    public static function templatesWithoutKnownTokens(): array
    {
        return [
            'unknown token' => ['Hello {{ nickname }}'],
            'unknown token with a fallback' => ['Hello {{ nickname | default: "there" }}'],
            'single braces' => ['Hello {event_title}'],
            'wrong case' => ['Hello {{ EVENT_TITLE }}'],
            'token split over lines keeps nothing else' => ['Hello {{ event title }}'],
            'no tokens' => ['Hello there'],
        ];
    }

    #[DataProvider('templatesWithoutKnownTokens')]
    public function test_anything_that_is_not_a_known_token_is_left_as_written(string $template): void
    {
        $renderer = new CampaignTokenRenderer;

        $this->assertSame($template, $renderer->renderSubject($template, self::VALUES));
        $this->assertSame($template, $renderer->renderBody($template, self::VALUES));
    }

    public function test_a_token_without_a_value_renders_as_nothing(): void
    {
        $renderer = new CampaignTokenRenderer;

        $this->assertSame('See you at', $renderer->renderSubject('See you at {{ event_title }}', ['event_title' => null]));
        $this->assertSame('<p>On .</p>', $renderer->renderBody('<p>On {{ event_date }}.</p>', ['event_date' => null]));
    }

    public function test_a_token_missing_from_the_values_is_left_for_later(): void
    {
        $renderer = new CampaignTokenRenderer;

        $this->assertSame('{{ event_date }} at Jazz', $renderer->renderSubject('{{ event_date }} at {{ organizer_name }}', ['organizer_name' => 'Jazz']));
        $this->assertSame('<p>On {{event_date}}.</p>', $renderer->renderBody('<p>On {{event_date}}.</p>', ['event_title' => 'Gig']));
    }

    public static function names(): array
    {
        return [
            'first name' => ['Hi {{ first_name }}', ['first_name' => 'Ada', 'last_name' => 'Lovelace'], 'Hi Ada'],
            'full name without spaces in the braces' => ['Hi {{first_name}} {{last_name}}', ['first_name' => 'Ada', 'last_name' => 'Lovelace'], 'Hi Ada Lovelace'],
            'missing name renders nothing' => ['Hi {{ first_name }}!', ['first_name' => null, 'last_name' => null], 'Hi !'],
            'empty name uses the fallback' => ['Hi {{ first_name | default: "there" }}!', ['first_name' => '', 'last_name' => null], 'Hi there!'],
            'missing name uses the fallback' => ['Hi {{ first_name|default:"friend" }}', ['first_name' => null], 'Hi friend'],
            'fallback without quotes' => ['Hi {{ first_name | default: there }}', ['first_name' => null], 'Hi there'],
            'fallback in single quotes' => ["Hi {{ first_name | default: 'Friend' }}", ['first_name' => null], 'Hi Friend'],
            'fallback with an apostrophe' => ['Hi {{ first_name | default: "y\'all" }}', ['first_name' => null], "Hi y'all"],
            'fallback is ignored when there is a name' => ['Hi {{ first_name | default: "there" }}', ['first_name' => 'Grace'], 'Hi Grace'],
            'name is put on one line' => ['Hi {{ first_name }}', ['first_name' => "Ada\r\nBcc: x@example.com"], 'Hi Ada Bcc: x@example.com'],
        ];
    }

    #[DataProvider('names')]
    public function test_name_tokens_render_the_recipients_name_or_a_fallback_in_the_subject(string $template, array $values, string $expected): void
    {
        $this->assertSame($expected, (new CampaignTokenRenderer)->renderSubject($template, $values));
    }

    public function test_name_tokens_are_html_escaped_in_the_body_and_the_fallback_survives_encoded_quotes(): void
    {
        $renderer = new CampaignTokenRenderer;

        $this->assertSame('<p>Hi &lt;b&gt;Ada&lt;/b&gt; &amp; co</p>', $renderer->renderBody('<p>Hi {{ first_name }}</p>', ['first_name' => '<b>Ada</b> & co']));
        $this->assertSame('<p>Hi there</p>', $renderer->renderBody('<p>Hi {{ first_name | default: &quot;there&quot; }}</p>', ['first_name' => null]));
        $this->assertSame('<p>Hi Ada</p>', $renderer->renderBody('<p>Hi {{ first_name | default: &quot;there&quot; }}</p>', ['first_name' => 'Ada']));
    }

    public static function placeholderChecks(): array
    {
        return [
            'plain token' => ['<p>Hi {{ first_name }}</p>', false],
            'fallback' => ['<p>Hi {{ first_name | default: &quot;there&quot; }}</p>', false],
            'single-quoted fallback' => ["Hi {{ first_name | default: 'there' }}", false],
            'unsupported filter' => ['<p>Hi {{ first_name | upcase }}</p>', true],
            'fallback plus another filter' => ['Hi {{ first_name | default: "x" | upcase }}', true],
        ];
    }

    #[DataProvider('placeholderChecks')]
    public function test_only_placeholders_the_renderer_can_render_pass_validation(string $text, bool $isRejected): void
    {
        $error = CampaignTokenCatalog::firstError($text, false);

        $this->assertSame($isRejected, $error !== null, (string) $error);

        if (! $isRejected) {
            $this->assertStringNotContainsString('{{', (new CampaignTokenRenderer)->renderBody($text, ['first_name' => null]));
        }
    }

    public function test_a_name_token_without_a_value_is_left_for_the_mail_to_render(): void
    {
        $template = '<p>Hi {{ first_name | default: "there" }}, news about {{ event_title }}</p>';

        $this->assertSame('<p>Hi {{ first_name | default: "there" }}, news about Gig</p>', (new CampaignTokenRenderer)->renderBody($template, ['event_title' => 'Gig']));
    }

    public function test_a_value_that_looks_like_a_token_is_not_expanded_again(): void
    {
        $values = ['event_title' => '{{ organizer_name }}', 'organizer_name' => 'SECRET'];

        $this->assertSame('{{ organizer_name }}', (new CampaignTokenRenderer)->renderSubject('{{ event_title }}', $values));
        $this->assertStringNotContainsString('SECRET', (new CampaignTokenRenderer)->renderBody('<p>{{ event_title }}</p>', $values));
    }

    public function test_every_default_template_only_uses_known_tokens_and_fits_the_column(): void
    {
        $defaults = new AutomationDefaults;

        foreach (CrmAutomationTrigger::cases() as $trigger) {
            $subject = $defaults->subject($trigger);
            $body = $defaults->body($trigger);

            $this->assertLessThanOrEqual(200, mb_strlen($subject), $trigger->value);
            $this->assertStringStartsWith('<p>', $body);
            preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $subject.$body, $matches);
            $this->assertSame([], array_values(array_diff($matches[1], CampaignTokenCatalog::all())), $trigger->value);

            if (! $trigger->isEventScoped()) {
                $this->assertNotContains('event_title', $matches[1], $trigger->value);
                $this->assertNotContains('event_date', $matches[1], $trigger->value);
            }
        }
    }
}
