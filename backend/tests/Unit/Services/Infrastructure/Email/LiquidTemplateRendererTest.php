<?php

namespace Tests\Unit\Services\Infrastructure\Email;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use Tests\TestCase;

class LiquidTemplateRendererTest extends TestCase
{
    private LiquidTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new LiquidTemplateRenderer();
    }

    public function test_can_render_simple_template_with_context(): void
    {
        $template = 'Hello {{ customer.name }}!';
        $context = [
            'customer' => [
                'name' => 'John Doe'
            ]
        ];

        $result = $this->renderer->render($template, $context);

        $this->assertEquals('Hello John Doe!', $result);
    }

    public function test_can_render_complex_template_with_nested_context(): void
    {
        $template = 'Order #{{ order.order_code }} for {{ event.title }} - Total: {{ order.total_gross_formatted }}';
        $context = [
            'order' => [
                'order_code' => 'ORD-123',
                'total_gross_formatted' => '$49.99'
            ],
            'event' => [
                'title' => 'Amazing Concert'
            ]
        ];

        $result = $this->renderer->render($template, $context);

        $this->assertEquals('Order #ORD-123 for Amazing Concert - Total: $49.99', $result);
    }

    public function test_can_render_template_with_loops(): void
    {
        $template = 'Items:{% for item in order.items %} {{ item.title }} ({{ item.quantity }}){% endfor %}';
        $context = [
            'order' => [
                'items' => [
                    ['title' => 'General Admission', 'quantity' => 2],
                    ['title' => 'VIP Pass', 'quantity' => 1]
                ]
            ]
        ];

        $result = $this->renderer->render($template, $context);

        $this->assertEquals('Items: General Admission (2) VIP Pass (1)', $result);
    }

    public function test_can_render_template_with_conditionals(): void
    {
        $template = '{% if customer.name %}Hello {{ customer.name }}{% else %}Hello Guest{% endif %}';
        
        $contextWithName = ['customer' => ['name' => 'Jane']];
        $contextWithoutName = ['customer' => []];

        $resultWithName = $this->renderer->render($template, $contextWithName);
        $resultWithoutName = $this->renderer->render($template, $contextWithoutName);

        $this->assertEquals('Hello Jane', $resultWithName);
        $this->assertEquals('Hello Guest', $resultWithoutName);
    }

    public function test_validates_correct_template_syntax(): void
    {
        $validTemplate = 'Hello {{ customer.name }}!';
        
        $result = $this->renderer->validate($validTemplate);
        
        $this->assertTrue($result);
    }

    public function test_validates_incorrect_template_syntax(): void
    {
        $invalidTemplate = 'Hello {% if %}'; // Invalid if syntax
        
        $result = $this->renderer->validate($invalidTemplate);
        
        $this->assertFalse($result);
    }

    public function test_returns_available_tokens_for_order_confirmation(): void
    {
        $tokens = $this->renderer->getAvailableTokens(EmailTemplateType::ORDER_CONFIRMATION);

        $this->assertIsArray($tokens);
        $this->assertNotEmpty($tokens);
        
        // Check that some expected tokens are present with new dot notation
        $tokenStrings = array_column($tokens, 'token');
        $this->assertContains('{{ order.number }}', $tokenStrings);
        $this->assertContains('{{ event.title }}', $tokenStrings);
        $this->assertContains('{{ organizer.name }}', $tokenStrings);
    }

    public function test_returns_available_tokens_for_attendee_ticket(): void
    {
        $tokens = $this->renderer->getAvailableTokens(EmailTemplateType::ATTENDEE_TICKET);

        $this->assertIsArray($tokens);
        $this->assertNotEmpty($tokens);
        
        // Check that some expected tokens are present with new dot notation
        $tokenStrings = array_column($tokens, 'token');
        $this->assertContains('{{ attendee.name }}', $tokenStrings);
        $this->assertContains('{{ ticket.name }}', $tokenStrings);
        $this->assertContains('{{ event.title }}', $tokenStrings);
        $this->assertContains('{{ ticket.url }}', $tokenStrings);
    }

    public function test_token_structure_contains_required_fields(): void
    {
        $tokens = $this->renderer->getAvailableTokens(EmailTemplateType::ORDER_CONFIRMATION);
        
        foreach ($tokens as $token) {
            $this->assertArrayHasKey('token', $token);
            $this->assertArrayHasKey('description', $token);
            $this->assertArrayHasKey('example', $token);
            
            $this->assertIsString($token['token']);
            $this->assertIsString($token['description']);
            $this->assertIsString($token['example']);
        }
    }

    public function test_handles_missing_context_gracefully(): void
    {
        $template = 'Hello {{ customer.name }}!';
        $context = []; // Empty context

        $result = $this->renderer->render($template, $context);

        // Liquid typically renders undefined variables as empty strings
        $this->assertEquals('Hello !', $result);
    }

    public function test_renders_html_content_as_expected(): void
    {
        $template = 'Message: {{ message }}';
        $context = [
            'message' => '<script>alert("xss")</script>'
        ];

        $result = $this->renderer->render($template, $context);

        // Test that the template renders the content
        $this->assertStringContainsString('Message: <script>alert("xss")</script>', $result);
    }
}