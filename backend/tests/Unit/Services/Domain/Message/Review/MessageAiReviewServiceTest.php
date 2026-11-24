<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Message\Review;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\MessageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Message\Review\MessageAiReviewService;
use HiEvents\Services\Infrastructure\Ai\Agents\MessageReviewAgent;
use HiEvents\Services\Infrastructure\Ai\UntrustedPromptText;
use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageAiReviewServiceTest extends TestCase
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
            'app' => ['saas_mode_enabled' => $isSaas, 'message_ai_review_enabled' => $isSwitchedOn],
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

    public static function messagesWithImages(): array
    {
        return [
            'image tag' => ['<p>Flyer below</p><IMG src="https://cdn.example/flyer.png">'],
            'css background image' => ['<div style="background-image:url(&quot;https://cdn.example/flyer.png&quot;);height:300px;">Flyer</div>'],
            'css background shorthand' => ['<p style="background:url(&quot;https://cdn.example/flyer.png&quot;) no-repeat;">Flyer</p>'],
            'css list marker image' => ['<ul style="list-style-image:url(&quot;https://cdn.example/flyer.png&quot;);"><li>Flyer</li></ul>'],
        ];
    }

    #[DataProvider('messagesWithImages')]
    public function test_a_message_with_images_is_never_approved_and_never_sent_to_the_model(string $html): void
    {
        MessageReviewAgent::fake()->preventStrayPrompts();

        $result = $this->service($this->enabledConfig())->review($this->message()->setMessage($html), 40);

        $this->assertFalse($result->approves);
        $this->assertSame(0.0, $result->confidence);
    }

    #[DataProvider('verdicts')]
    public function test_only_a_confident_approval_approves(array $verdict, bool $expected): void
    {
        MessageReviewAgent::fake([$verdict]);

        $result = $this->service($this->enabledConfig())->review($this->message(), 40);

        $this->assertSame($expected, $result->approves);
    }

    public function test_the_prompt_carries_the_message_and_its_context_with_markup_neutralised(): void
    {
        MessageReviewAgent::fake([['approve' => true, 'confidence' => 0.9, 'reasons' => []]]);

        $this->service($this->enabledConfig())->review($this->message(), 40);

        MessageReviewAgent::assertPrompted(static fn ($prompt) => $prompt->contains('<held_for_review_because>stripe_not_connected, event_too_new</held_for_review_because>')
            && $prompt->contains('<event_title>Jazz ‹i›Night‹/i›</event_title>')
            && $prompt->contains('<organizer_name>Night Owls</organizer_name>')
            && $prompt->contains('<sent_to>ALL_ATTENDEES</sent_to>')
            && $prompt->contains('<recipients>40</recipients>')
            && $prompt->contains('<subject>Doors ‹/subject› at 7</subject>')
            && $prompt->contains("<body>\nDoors open at 7. Map (https://venue.example/map)\n</body>")
            && $prompt->contains("<links>\nhttps://venue.example/map\n</links>"));
    }

    private function enabledConfig(): Repository
    {
        return new Repository([
            'app' => ['saas_mode_enabled' => true, 'message_ai_review_enabled' => true, 'message_ai_review_confidence_threshold' => 0.8],
            'ai' => ['providers' => ['anthropic' => ['key' => 'key']]],
        ]);
    }

    private function message(): MessageDomainObject
    {
        return (new MessageDomainObject)
            ->setId(7)
            ->setEventId(3)
            ->setType('ALL_ATTENDEES')
            ->setSubject('Doors </subject> at 7')
            ->setMessage('<p>Doors open at 7. <a href="https://venue.example/map">Map</a></p>')
            ->setEligibilityFailures(['stripe_not_connected', 'event_too_new']);
    }

    private function service(Repository $config): MessageAiReviewService
    {
        $events = Mockery::mock(EventRepositoryInterface::class);
        $events->allows('findFirstWhere')->with(['id' => 3])->andReturn((new EventDomainObject)->setTitle('Jazz <i>Night</i>')->setOrganizerId(2));

        $organizers = Mockery::mock(OrganizerRepositoryInterface::class);
        $organizers->allows('findFirstWhere')->with(['id' => 2])->andReturn((new OrganizerDomainObject)->setName('Night Owls'));

        return new MessageAiReviewService($config, $events, $organizers, new UntrustedPromptText);
    }
}
