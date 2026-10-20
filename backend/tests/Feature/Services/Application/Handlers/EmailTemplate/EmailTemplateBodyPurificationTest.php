<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Application\Handlers\EmailTemplate;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Models\User;
use HiEvents\Services\Application\Handlers\EmailTemplate\CreateEmailTemplateHandler;
use HiEvents\Services\Application\Handlers\EmailTemplate\DTO\UpsertEmailTemplateDTO;
use HiEvents\Services\Application\Handlers\EmailTemplate\UpdateEmailTemplateHandler;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailTemplateBodyPurificationTest extends TestCase
{
    use DatabaseTransactions;

    private const BODY = '<p>Hi {{ order.first_name }}</p>'
        .'<p><a href="https://example.com/orders?ref={{ order.number }}">View your order</a></p>'
        .'<p>{{ \'<img src=x onerror=alert(1)>\' }}</p>';

    private int $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->accountId = $user->accounts()->first()->id;
    }

    public function test_creating_a_template_keeps_liquid_tokens_in_links(): void
    {
        $template = $this->app->make(CreateEmailTemplateHandler::class)->handle($this->dto());

        $stored = DB::table('email_templates')->where('id', $template->getId())->value('body');

        $this->assertStringContainsString('href="https://example.com/orders?ref={{ order.number }}"', $stored);
        $this->assertStringNotContainsString('%7B%7B', $stored);
    }

    public function test_creating_a_template_still_strips_markup_smuggled_through_a_token(): void
    {
        $template = $this->app->make(CreateEmailTemplateHandler::class)->handle($this->dto());

        $stored = DB::table('email_templates')->where('id', $template->getId())->value('body');

        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function test_updating_a_template_keeps_liquid_tokens_in_links(): void
    {
        $created = $this->app->make(CreateEmailTemplateHandler::class)->handle($this->dto());

        $updated = $this->app->make(UpdateEmailTemplateHandler::class)->handle($this->dto($created->getId()));

        $stored = DB::table('email_templates')->where('id', $updated->getId())->value('body');

        $this->assertStringContainsString('href="https://example.com/orders?ref={{ order.number }}"', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function test_a_stored_token_resolves_to_a_real_value_when_rendered(): void
    {
        $template = $this->app->make(CreateEmailTemplateHandler::class)->handle($this->dto());

        $stored = (string) DB::table('email_templates')->where('id', $template->getId())->value('body');

        $rendered = $this->app->make(EmailTemplateService::class)->previewTemplate(
            'Your order',
            $stored,
            EmailTemplateType::ORDER_CONFIRMATION,
        )['body'];

        $this->assertMatchesRegularExpression(
            '/href="https:\/\/example\.com\/orders\?ref=[^"{%]+"/',
            $rendered,
        );
        $this->assertStringNotContainsString('{{', $rendered);
        $this->assertStringNotContainsString('onerror', $rendered);
    }

    private function dto(?int $id = null): UpsertEmailTemplateDTO
    {
        return new UpsertEmailTemplateDTO(
            account_id: $this->accountId,
            template_type: EmailTemplateType::ORDER_CONFIRMATION,
            subject: 'Your order',
            body: self::BODY,
            id: $id,
            cta: ['label' => 'View order', 'url_token' => 'order.url'],
        );
    }
}
