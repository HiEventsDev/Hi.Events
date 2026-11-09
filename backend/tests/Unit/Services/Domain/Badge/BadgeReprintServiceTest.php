<?php

namespace Tests\Unit\Services\Domain\Badge;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BadgeBackMode;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeSheet;
use HiEvents\DomainObjects\Enums\BadgeSize;
use HiEvents\DomainObjects\Enums\BadgeTemplate;
use HiEvents\DomainObjects\Enums\BadgeTextSize;
use HiEvents\DomainObjects\Status\BadgePrintStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeDesignService;
use HiEvents\Services\Domain\Badge\BadgeReprintService;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeLineDTO;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BadgeReprintServiceTest extends TestCase
{
    private MockInterface|AttendeeRepositoryInterface $attendeeRepository;

    private MockInterface|BadgeDesignService $badgeDesignService;

    private BadgeReprintService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->badgeDesignService = Mockery::mock(BadgeDesignService::class);

        $this->service = new BadgeReprintService($this->attendeeRepository, $this->badgeDesignService);
    }

    public function test_marking_printed_increments_the_count_for_the_events_attendees(): void
    {
        $this->attendeeRepository->shouldReceive('incrementEach')
            ->once()
            ->withArgs(fn (array $columns, array $updates, array $where) => $columns === ['badge_print_count' => 1]
                && array_key_exists('badge_printed_at', $updates)
                && $where === ['event_id' => 4, ['id', 'in', [1, 2]]])
            ->andReturn(2);

        $this->assertSame(2, $this->service->markPrinted(4, [1, 2]));
    }

    public function test_marking_nothing_printed_skips_the_query(): void
    {
        $this->attendeeRepository->shouldNotReceive('incrementEach');

        $this->assertSame(0, $this->service->markPrinted(4, []));
    }

    public function test_a_printed_badge_whose_name_changed_needs_a_reprint(): void
    {
        $this->attendeeRepository->shouldReceive('updateWhere')
            ->once()
            ->with(['badge_printed_at' => null], [['id', 'in', [9]], ['badge_printed_at', 'not null', null]]);

        $after = $this->service->flagIfBadgeDetailsChanged(
            $this->printedAttendee('Ada'),
            $this->printedAttendee('Augusta'),
        );

        $this->assertSame(BadgePrintStatus::NEEDS_REPRINT, $after->getBadgePrintStatus());
    }

    public function test_unchanged_or_unprinted_badges_are_left_alone(): void
    {
        $this->attendeeRepository->shouldNotReceive('updateWhere');

        $unchanged = $this->service->flagIfBadgeDetailsChanged($this->printedAttendee('Ada'), $this->printedAttendee('Ada'));
        $neverPrinted = $this->service->flagIfBadgeDetailsChanged(
            $this->printedAttendee('Ada')->setBadgePrintedAt(null)->setBadgePrintCount(0),
            $this->printedAttendee('Augusta')->setBadgePrintedAt(null)->setBadgePrintCount(0),
        );

        $this->assertSame(BadgePrintStatus::PRINTED, $unchanged->getBadgePrintStatus());
        $this->assertSame(BadgePrintStatus::NOT_PRINTED, $neverPrinted->getBadgePrintStatus());
    }

    public function test_an_order_answer_on_the_badge_flags_every_attendee_on_the_order(): void
    {
        $this->badgeDesignService->shouldReceive('getDesign')->with(4)->andReturn($this->designWithQuestion(30));
        $this->attendeeRepository->shouldReceive('findWhere')
            ->with(['order_id' => 70])
            ->andReturn(collect([(new AttendeeDomainObject)->setId(1), (new AttendeeDomainObject)->setId(2)]));
        $this->attendeeRepository->shouldReceive('updateWhere')
            ->once()
            ->with(['badge_printed_at' => null], [['id', 'in', [1, 2]], ['badge_printed_at', 'not null', null]]);

        $this->service->flagForAnswerChange(eventId: 4, questionId: 30, orderId: 70, attendeeId: null);
    }

    public function test_an_answer_that_is_not_on_the_badge_changes_nothing(): void
    {
        $this->badgeDesignService->shouldReceive('getDesign')->with(4)->andReturn($this->designWithQuestion(30));
        $this->attendeeRepository->shouldNotReceive('findWhere');
        $this->attendeeRepository->shouldNotReceive('updateWhere');

        $this->service->flagForAnswerChange(eventId: 4, questionId: 31, orderId: 70, attendeeId: 1);
    }

    private function printedAttendee(string $firstName): AttendeeDomainObject
    {
        return (new AttendeeDomainObject)
            ->setId(9)
            ->setFirstName($firstName)
            ->setLastName('Lovelace')
            ->setProductId(3)
            ->setSeatLabel(null)
            ->setBadgePrintCount(1)
            ->setBadgePrintedAt('2027-01-01 10:00:00');
    }

    private function designWithQuestion(int $questionId): BadgeDesignDTO
    {
        return new BadgeDesignDTO(
            template: BadgeTemplate::CLASSIC,
            size: BadgeSize::A6_PORTRAIT,
            custom_width_mm: null,
            custom_height_mm: null,
            sheet: BadgeSheet::A4,
            accent_color: '#123456',
            background_color: null,
            text_color: null,
            font_family: 'Inter',
            text_align: null,
            uppercase_names: false,
            lines: [
                new BadgeLineDTO(BadgeFieldSource::FIRST_NAME, BadgeTextSize::XL),
                new BadgeLineDTO(BadgeFieldSource::QUESTION, BadgeTextSize::M, question_id: $questionId),
            ],
            show_logo: true,
            show_qr: true,
            show_ribbon: true,
            show_seat: true,
            show_event_name: true,
            show_sponsors: true,
            footer_text: null,
            ribbons: [],
            back_mode: BadgeBackMode::NONE,
            back_text: null,
        );
    }
}
