<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\LifecycleEmail;

use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\DomainObjects\LifecycleEmailTemplateDomainObject;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Repository\Interfaces\AccountLifecycleNudgeRepositoryInterface;
use HiEvents\Repository\Interfaces\LifecycleEmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailContextHtmlEscaper;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailTemplateService;
use HiEvents\Services\Domain\LifecycleEmail\LifecycleEmailUnsubscribeTokens;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\EventDraftStaleNudge;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeRegistry;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LiveEventNoSalesNudge;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\NoEventCreatedNudge;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\StripeOnboardingIncompleteNudge;
use HiEvents\Services\Domain\Payment\Stripe\StripeConnectTimezones;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use HTMLPurifier;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LifecycleEmailTemplateServiceTest extends TestCase
{
    private LifecycleEmailTemplateRepositoryInterface|MockInterface $repository;

    private LifecycleEmailTemplateService $service;

    private LifecycleNudgeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 12:00:00');

        $this->repository = Mockery::mock(LifecycleEmailTemplateRepositoryInterface::class);
        $nudgeRepository = Mockery::mock(AccountLifecycleNudgeRepositoryInterface::class);
        $this->registry = new LifecycleNudgeRegistry(
            new NoEventCreatedNudge($nudgeRepository),
            new StripeOnboardingIncompleteNudge($nudgeRepository, new StripeConnectTimezones),
            new EventDraftStaleNudge($nudgeRepository),
            new LiveEventNoSalesNudge($nudgeRepository),
        );
        $this->service = new LifecycleEmailTemplateService(
            templateRepository: $this->repository,
            nudgeRegistry: $this->registry,
            liquidRenderer: new LiquidTemplateRenderer,
            contextHtmlEscaper: new EmailContextHtmlEscaper,
            purifier: new HtmlPurifierService(new HTMLPurifier),
            unsubscribeTokens: new LifecycleEmailUnsubscribeTokens(new ConfigRepository(['app' => ['key' => 'test-key']])),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_find_all_returns_disabled_defaults_when_nothing_is_saved(): void
    {
        $this->repository->shouldReceive('all')->andReturn(collect());

        $templates = $this->service->findAll();

        $this->assertCount(count(LifecycleNudgeType::cases()), $templates);
        foreach ($templates as $template) {
            $this->assertNull($template->enabledAt);
            $this->assertFalse($template->isCustomised);
            $this->assertNotSame('', $template->subject);
        }
    }

    public function test_find_all_prefers_the_saved_template(): void
    {
        $this->repository->shouldReceive('all')->andReturn(collect([
            $this->savedTemplate(LifecycleNudgeType::EVENT_DRAFT_STALE, enabledAt: '2026-10-01 00:00:00'),
        ]));

        $stale = $this->service->findAll()
            ->first(fn ($template) => $template->nudgeType === LifecycleNudgeType::EVENT_DRAFT_STALE);

        $this->assertSame('Saved subject', $stale->subject);
        $this->assertSame('2026-10-01 00:00:00', $stale->enabledAt);
        $this->assertTrue($stale->isCustomised);
    }

    public function test_enabling_a_new_template_creates_it_enabled_now(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturn(null, $this->savedTemplate(LifecycleNudgeType::EVENT_DRAFT_STALE));
        $this->repository->shouldReceive('create')->once()->with(Mockery::on(function (array $attributes) {
            return $attributes['nudge_type'] === 'EVENT_DRAFT_STALE'
                && $attributes['enabled_at']->equalTo(Carbon::now());
        }));

        $this->service->save(LifecycleNudgeType::EVENT_DRAFT_STALE, 'Subject', '<p>Body</p>', 'Go', enabled: true);
    }

    public function test_re_saving_an_enabled_template_keeps_its_original_enabled_at(): void
    {
        $saved = $this->savedTemplate(LifecycleNudgeType::EVENT_DRAFT_STALE, enabledAt: '2026-10-01 00:00:00');
        $this->repository->shouldReceive('findFirstWhere')->andReturn($saved);
        $this->repository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['enabled_at'] === '2026-10-01 00:00:00'),
            ['nudge_type' => 'EVENT_DRAFT_STALE'],
        );

        $this->service->save(LifecycleNudgeType::EVENT_DRAFT_STALE, 'Subject', '<p>Body</p>', 'Go', enabled: true);
    }

    public function test_disabling_clears_enabled_at(): void
    {
        $saved = $this->savedTemplate(LifecycleNudgeType::EVENT_DRAFT_STALE, enabledAt: '2026-10-01 00:00:00');
        $this->repository->shouldReceive('findFirstWhere')->andReturn($saved);
        $this->repository->shouldReceive('updateWhere')->once()->with(
            Mockery::on(fn (array $attributes) => $attributes['enabled_at'] === null),
            Mockery::any(),
        );

        $this->service->save(LifecycleNudgeType::EVENT_DRAFT_STALE, 'Subject', '<p>Body</p>', 'Go', enabled: false);
    }

    public function test_save_purifies_the_body(): void
    {
        $this->repository->shouldReceive('findFirstWhere')->andReturn(null, $this->savedTemplate(LifecycleNudgeType::EVENT_DRAFT_STALE));
        $this->repository->shouldReceive('create')->once()->with(Mockery::on(
            fn (array $attributes) => ! str_contains($attributes['body'], '<script')
                && str_contains($attributes['body'], '{{ first_name }}'),
        ));

        $this->service->save(
            LifecycleNudgeType::EVENT_DRAFT_STALE,
            'Subject',
            '<p>Hi {{ first_name }}</p><script>alert(1)</script>',
            'Go',
            enabled: false,
        );
    }

    public function test_save_rejects_invalid_liquid_without_writing(): void
    {
        $this->repository->shouldNotReceive('create');
        $this->repository->shouldNotReceive('updateWhere');

        try {
            $this->service->save(LifecycleNudgeType::EVENT_DRAFT_STALE, '{% if %}', '<p>Body</p>', 'Go', enabled: true);
            $this->fail('Expected a validation exception');
        } catch (EmailTemplateValidationException $e) {
            $this->assertArrayHasKey('subject', $e->validationErrors);
        }
    }

    public function test_save_rejects_a_template_that_fails_at_render_time(): void
    {
        $this->repository->shouldNotReceive('create');
        $this->repository->shouldNotReceive('updateWhere');

        try {
            $this->service->save(LifecycleNudgeType::EVENT_DRAFT_STALE, 'Subject', '<p>{{ 1 | divided_by: 0 }}</p>', 'Go', enabled: true);
            $this->fail('Expected a validation exception');
        } catch (EmailTemplateValidationException $e) {
            $this->assertArrayHasKey('body', $e->validationErrors);
        }
    }

    public function test_liquid_filters_cannot_smuggle_html_past_the_purifier(): void
    {
        $template = new LifecycleEmailTemplateDTO(
            nudgeType: LifecycleNudgeType::EVENT_DRAFT_STALE,
            subject: 'Subject',
            body: '<p>{{ "%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E" | url_decode }}</p>',
            ctaLabel: 'Go',
        );

        $html = $this->service->buildMail($template, $this->registry->for(LifecycleNudgeType::EVENT_DRAFT_STALE)->sampleRecipient())->render();

        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_build_mail_escapes_values_in_the_body_but_not_the_subject(): void
    {
        $template = $this->registry->for(LifecycleNudgeType::EVENT_DRAFT_STALE)->defaultTemplate();

        $mail = $this->service->buildMail($template, new LifecycleEmailRecipientDTO(
            firstName: 'Sam',
            ctaUrl: 'https://example.com/manage/event/1/dashboard',
            context: ['event' => ['title' => 'Fish & <Chips>']],
        ));

        $this->assertSame('Fish & <Chips> is waiting to be published', $mail->envelope()->subject);
        $this->assertStringContainsString('Fish &amp; &lt;Chips&gt;', $mail->render());
        $this->assertStringContainsString('https://example.com/manage/event/1/dashboard', $mail->render());
    }

    public function test_every_nudge_type_has_a_nudge_whose_defaults_render_with_its_sample_data(): void
    {
        foreach (LifecycleNudgeType::cases() as $type) {
            $nudge = $this->registry->for($type);

            $this->assertSame($type, $nudge->type());
            $this->assertSame($type, $nudge->defaultTemplate()->nudgeType);

            $mail = $this->service->buildMail($nudge->defaultTemplate(), $nudge->sampleRecipient());
            $html = $mail->render();

            $this->assertStringNotContainsString('{{', $mail->envelope()->subject, $type->name);
            $this->assertStringNotContainsString('{{', $html, $type->name);
        }
    }

    public function test_every_token_a_nudge_offers_resolves_against_its_sample_data(): void
    {
        foreach ($this->registry->all() as $nudge) {
            $tokens = implode(' ', array_column($nudge->tokens(), 'token'));
            $template = new LifecycleEmailTemplateDTO(
                nudgeType: $nudge->type(),
                subject: $tokens,
                body: '<p>Body</p>',
                ctaLabel: 'Go',
            );

            $subject = $this->service->buildMail($template, $nudge->sampleRecipient())->envelope()->subject;

            foreach ($nudge->tokens() as $token) {
                $this->assertStringContainsString($token['example'], $subject, $nudge->type()->name.' '.$token['token']);
            }
        }
    }

    private function savedTemplate(LifecycleNudgeType $type, ?string $enabledAt = null): LifecycleEmailTemplateDomainObject
    {
        return (new LifecycleEmailTemplateDomainObject)
            ->setNudgeType($type->name)
            ->setSubject('Saved subject')
            ->setBody('<p>Saved body</p>')
            ->setCtaLabel('Saved CTA')
            ->setEnabledAt($enabledAt);
    }
}
