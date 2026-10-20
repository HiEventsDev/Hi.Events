<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckContentService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use HiEvents\Services\Domain\Lineup\LineupHighlightSelector;
use HiEvents\Services\Infrastructure\Ai\Agents\EventSpamDetectionAgent;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EventSpamCheckPromptBudgetTest extends TestCase
{
    private const PHISHING = 'Call +1 805 555 0100 to cancel your $364 charge';

    private EventSpamCheckContentService $contentService;

    private EventSpamCheckService $checkService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentService = new EventSpamCheckContentService(Mockery::mock(EventRepositoryInterface::class), new LineupHighlightSelector);
        $this->checkService = new EventSpamCheckService(new Repository([
            'app' => ['event_spam_check_confidence_threshold' => 0.7],
        ]));

        EventSpamDetectionAgent::fake([['is_spam' => false, 'confidence' => 0.9, 'reasons' => []]]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_headliner_names_and_slot_titles_reach_the_prompt_behind_padded_bios_and_slot_notes(): void
    {
        $entries = [];

        foreach (range(1, 9) as $id) {
            $entries[] = $this->entry($id, 'STANDARD', sortOrder: $id - 1, name: "Opener $id", bio: $this->padding());
        }

        $entries[] = $this->entry(10, 'HEADLINER', sortOrder: 9, name: self::PHISHING);

        $items = [];

        foreach (range(1, 9) as $id) {
            $items[] = (new EventScheduleItemDomainObject)
                ->setId($id)
                ->setStartsAt(sprintf('2030-06-14 %02d:00:00', 10 + $id))
                ->setDescription($this->padding());
        }

        $items[] = (new EventScheduleItemDomainObject)
            ->setId(10)
            ->setStartsAt('2030-06-14 23:00:00')
            ->setTitle('Refund desk: call +1 805 555 0199');

        $this->check($this->event()
            ->setEventLineupEntries(new Collection($entries))
            ->setEventScheduleItems(new Collection($items)));

        EventSpamDetectionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, ': '.self::PHISHING)
            && str_contains($prompt->prompt, ': Refund desk: call +1 805 555 0199'));
    }

    public function test_email_slot_title_and_stage_reach_the_prompt_behind_a_long_lineup(): void
    {
        $entries = array_map(
            fn (int $id) => $this->entry($id, 'STANDARD', sortOrder: $id, name: str_pad("Act $id ", 250, 'x')),
            range(1, 16),
        );

        $this->check($this->event()
            ->setEventLineupEntries(new Collection($entries))
            ->setEventSpaces(new Collection([
                (new EventSpaceDomainObject)->setId(1)->setName('Side room'),
                (new EventSpaceDomainObject)->setId(7)->setName('Text 805 555 0123 for refunds'),
            ]))
            ->setEventScheduleItems(new Collection([
                (new EventScheduleItemDomainObject)
                    ->setId(1)
                    ->setEventSpaceId(7)
                    ->setStartsAt('2030-06-14 20:00:00')
                    ->setTitle('Refund desk: call +1 805 555 0199'),
            ])));

        EventSpamDetectionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, ': Refund desk: call +1 805 555 0199')
            && str_contains($prompt->prompt, ': Text 805 555 0123 for refunds'));
    }

    public function test_headliner_slot_title_reaches_the_prompt_behind_earlier_padded_slots(): void
    {
        $items = array_map(
            fn (int $id) => (new EventScheduleItemDomainObject)
                ->setId($id)
                ->setStartsAt(sprintf('2030-06-14 %02d:00:00', $id))
                ->setTitle(str_pad("Workshop $id ", 250, 'x')),
            range(1, 20),
        );

        $items[] = (new EventScheduleItemDomainObject)
            ->setId(21)
            ->setStartsAt('2030-06-14 23:00:00')
            ->setTitle(self::PHISHING)
            ->setPerformers(new Collection([(new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId(1)]));

        $this->check($this->event()
            ->setEventLineupEntries(new Collection([$this->entry(1, 'HEADLINER', sortOrder: 0, name: 'Nova Lights')]))
            ->setEventScheduleItems(new Collection($items)));

        EventSpamDetectionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, ': '.self::PHISHING));
    }

    public function test_product_titles_reach_the_prompt_behind_padded_descriptions_and_homepage_sections(): void
    {
        $products = [];

        foreach (range(1, 9) as $index) {
            $products[] = (new ProductDomainObject)->setTitle("Ticket $index")->setDescription($this->padding());
        }

        $products[] = (new ProductDomainObject)->setTitle(self::PHISHING);

        $this->check($this->event()
            ->setOrganizer($this->organizerWithPaddedHomepage())
            ->setProducts(new Collection($products)));

        EventSpamDetectionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'product 10 title: '.self::PHISHING));
    }

    public function test_email_messages_reach_the_prompt_behind_padded_organizer_homepage_sections(): void
    {
        $this->check($this->event()
            ->setOrganizer($this->organizerWithPaddedHomepage())
            ->setEventSettings((new EventSettingDomainObject)
                ->setEmailFooterMessage('<p>'.self::PHISHING.'</p>')
                ->setOfflinePaymentInstructions('<p>Wire the fee to account 12345</p>')));

        EventSpamDetectionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'email footer message: '.self::PHISHING)
            && str_contains($prompt->prompt, 'offline payment instructions: Wire the fee to account 12345'));
    }

    public function test_prompt_leads_with_what_ticket_emails_show_and_keeps_the_rest_behind_email_messages(): void
    {
        $entry = fn (int $id, string $tier, int $sortOrder, string $name) => $this->entry($id, $tier, $sortOrder, $name)->setRoleLabel("$name role");
        $performers = fn (int ...$entryIds) => new Collection(array_map(
            fn (int $entryId) => (new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId($entryId),
            $entryIds,
        ));

        $this->check($this->event()
            ->setOrganizer((new OrganizerDomainObject)->setName('Promoter')->setDescription('<p>About us</p>'))
            ->setEventSettings((new EventSettingDomainObject)->setPreCheckoutMessage('Pre checkout')->setEmailFooterMessage('Footer'))
            ->setProducts(new Collection([(new ProductDomainObject)->setTitle('Day ticket')->setDescription('<p>Entry</p>')]))
            ->setEventSpaces(new Collection([
                (new EventSpaceDomainObject)->setId(10)->setName('Main Stage'),
                (new EventSpaceDomainObject)->setId(11)->setName('Tent'),
            ]))
            ->setEventLineupEntries(new Collection([
                $entry(1, 'HEADLINER', 0, 'Nova'),
                $entry(2, 'HEADLINER', 1, 'Orbit'),
                $entry(3, 'STANDARD', 2, 'Opener'),
            ]))
            ->setEventScheduleItems(new Collection([
                (new EventScheduleItemDomainObject)->setId(1)->setStartsAt('2030-06-14 18:00:00')->setEventSpaceId(11)->setTitle('Doors'),
                (new EventScheduleItemDomainObject)->setId(2)->setStartsAt('2030-06-14 21:00:00')->setEventSpaceId(10)->setPerformers($performers(1)),
                (new EventScheduleItemDomainObject)->setId(3)->setStartsAt('2030-06-14 22:00:00')->setTitle('Encore')->setPerformers($performers(2, 3)),
            ])));

        EventSpamDetectionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, implode("\n", [
            'organizer name: Promoter',
            'ticket email lineup 2 name: Orbit',
            'ticket email lineup 1 name: Nova',
            'ticket email space 1 name: Main Stage',
            'ticket email schedule item 3 title: Encore',
            'product 1 title: Day ticket',
            'email footer message: Footer',
            'lineup 3 name: Opener',
            'schedule item 1 title: Doors',
            'space 2 name: Tent',
            'organizer description: About us',
            'pre-checkout message: Pre checkout',
            'product 1 description: Entry',
            'lineup 1 role: Nova role',
        ])));
    }

    private function check(EventDomainObject $event): void
    {
        $this->checkService->checkContent($this->contentService->buildForEvent($event));
    }

    private function event(): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Summer Festival')
            ->setDescription('<p>Three days of music</p>');
    }

    private function organizerWithPaddedHomepage(): OrganizerDomainObject
    {
        return (new OrganizerDomainObject)
            ->setName('Promoter')
            ->setDescription($this->padding())
            ->setOrganizerSettings((new OrganizerSettingDomainObject)->setHomepageSections(array_map(
                fn (int $index) => ['id' => "s$index", 'type' => 'TEXT', 'heading' => "Section $index", 'body' => $this->padding()],
                range(1, 9),
            )));
    }

    private function entry(int $id, string $tier, int $sortOrder, string $name, ?string $bio = null): EventLineupEntryDomainObject
    {
        return (new EventLineupEntryDomainObject)
            ->setId($id)
            ->setBillingTier($tier)
            ->setSortOrder($sortOrder)
            ->setIsHidden(false)
            ->setProfile((new ProfileDomainObject)->setName($name)->setBio($bio));
    }

    private function padding(): string
    {
        return '<p>'.str_repeat('Long biography text that fills the budget. ', 15).'</p>';
    }
}
