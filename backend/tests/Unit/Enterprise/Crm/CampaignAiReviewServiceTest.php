<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\CrmMarketingChannel;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignMailFactory;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignAiReviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\Review\CampaignReviewAgent;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\CampaignSmsPreviewService;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Sms\SmsPreviewVariantDTO;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Infrastructure\Ai\UntrustedPromptText;
use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignAiReviewServiceTest extends TestCase
{
    public static function switches(): array
    {
        return [
            'everything on' => [true, true, 'key', true],
            'self-hosted' => [false, true, 'key', false],
            'switched off' => [true, false, 'key', false],
            'no anthropic key' => [true, true, null, false],
        ];
    }

    #[DataProvider('switches')]
    public function test_it_only_runs_in_saas_mode_when_switched_on_with_an_anthropic_key(bool $isSaas, bool $isSwitchedOn, ?string $key, bool $expected): void
    {
        $service = $this->service(new Repository([
            'app' => ['saas_mode_enabled' => $isSaas, 'crm_campaign_ai_review_enabled' => $isSwitchedOn],
            'ai' => ['providers' => ['anthropic' => ['key' => $key]]],
        ]));

        $this->assertSame($expected, (bool) $service->isEnabled());
    }

    public static function verdicts(): array
    {
        return [
            'approved with confidence at the threshold' => [['approve' => true, 'confidence' => 0.8, 'reasons' => ['Event reminder']], true],
            'approved just under the threshold' => [['approve' => true, 'confidence' => 0.79, 'reasons' => []], false],
            'not approved however confident' => [['approve' => false, 'confidence' => 1.0, 'reasons' => ['Phishing']], false],
            'verdict missing its fields' => [[], false],
        ];
    }

    #[DataProvider('verdicts')]
    public function test_only_a_confident_approval_approves(array $verdict, bool $expected): void
    {
        CampaignReviewAgent::fake([$verdict]);

        $result = $this->service($this->enabledConfig())->review($this->textCampaign(), 40);

        $this->assertSame($expected, $result->approves);
    }

    public function test_a_text_campaign_is_checked_as_recipients_will_read_it(): void
    {
        CampaignReviewAgent::fake([['approve' => true, 'confidence' => 0.9, 'reasons' => []]]);

        $this->service($this->enabledConfig())->review($this->textCampaign(), 40);

        CampaignReviewAgent::assertPrompted(static fn ($prompt) => $prompt->contains('<channel>text message</channel>')
            && $prompt->contains('Doors at 7. Tickets: https://tickets.example/e/1 Reply STOP to opt out')
            && $prompt->contains("<links>\nhttps://tickets.example/e/1\n</links>")
            && $prompt->contains('<organizer_name>Night ‹b›Owls‹/b›</organizer_name>')
            && $prompt->contains('<recipients>40</recipients>')
            && ! $prompt->contains('<subject>'));
    }

    private function enabledConfig(): Repository
    {
        return new Repository([
            'app' => ['saas_mode_enabled' => true, 'crm_campaign_ai_review_enabled' => true, 'crm_campaign_ai_review_confidence_threshold' => 0.8],
            'ai' => ['providers' => ['anthropic' => ['key' => 'key']]],
        ]);
    }

    private function textCampaign(): CrmCampaignDomainObject
    {
        return (new CrmCampaignDomainObject)
            ->setId(7)
            ->setAccountId(1)
            ->setOrganizerId(2)
            ->setChannel(CrmMarketingChannel::SMS->value)
            ->setStatusReason('FIRST_CAMPAIGN')
            ->setBody('Doors at 7. Tickets: {{ event_url }}');
    }

    private function service(Repository $config): CampaignAiReviewService
    {
        $smsPreview = Mockery::mock(CampaignSmsPreviewService::class);
        $smsPreview->allows('preview')->andReturn(new SmsPreviewDTO(
            variants: [new SmsPreviewVariantDTO(['US'], 'Doors at 7. Tickets: https://tickets.example/e/1 Reply STOP to opt out', 70, 1, false)],
            uses_placeholders: true,
        ));

        $accounts = Mockery::mock(AccountRepositoryInterface::class);
        $accounts->allows('findById')->andReturn((new AccountDomainObject)->setCreatedAt(now()->subDays(30)->toIso8601String()));

        $organizers = Mockery::mock(OrganizerRepositoryInterface::class);
        $organizers->allows('findFirstWhere')->andReturn((new OrganizerDomainObject)->setName('Night <b>Owls</b>'));

        return new CampaignAiReviewService(
            $config,
            Mockery::mock(CampaignMailFactory::class),
            $smsPreview,
            Mockery::mock(CrmCampaignRepositoryInterface::class),
            $accounts,
            $organizers,
            Mockery::mock(EventRepositoryInterface::class),
            new UntrustedPromptText,
        );
    }
}
