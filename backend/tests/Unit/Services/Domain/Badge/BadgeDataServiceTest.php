<?php

namespace Tests\Unit\Services\Domain\Badge;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BadgeBackMode;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeSheet;
use HiEvents\DomainObjects\Enums\BadgeSize;
use HiEvents\DomainObjects\Enums\BadgeTemplate;
use HiEvents\DomainObjects\Enums\BadgeTextSize;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionAnswerDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\DomainObjects\Status\BadgePrintStatus;
use HiEvents\Repository\Interfaces\QuestionAnswerRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeDataService;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeLineDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeRibbonDTO;
use HiEvents\Services\Domain\Question\QuestionAnswerFormatter;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BadgeDataServiceTest extends TestCase
{
    private const EVENT_ID = 7;

    private MockInterface|QuestionRepositoryInterface $questionRepository;

    private MockInterface|QuestionAnswerRepositoryInterface $questionAnswerRepository;

    private BadgeDataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->questionRepository = Mockery::mock(QuestionRepositoryInterface::class);
        $this->questionAnswerRepository = Mockery::mock(QuestionAnswerRepositoryInterface::class);

        $this->service = new BadgeDataService(
            $this->questionRepository,
            $this->questionAnswerRepository,
            new QuestionAnswerFormatter,
        );
    }

    public function test_name_ticket_date_and_custom_lines_resolve_without_touching_questions(): void
    {
        $design = $this->design(lines: [
            new BadgeLineDTO(BadgeFieldSource::FULL_NAME, BadgeTextSize::XL),
            new BadgeLineDTO(BadgeFieldSource::TICKET_TYPE, BadgeTextSize::M),
            new BadgeLineDTO(BadgeFieldSource::OCCURRENCE_DATE, BadgeTextSize::S),
            new BadgeLineDTO(BadgeFieldSource::CUSTOM_TEXT, BadgeTextSize::S, text: 'Ask me anything'),
        ]);

        $this->questionRepository->shouldNotReceive('findWhereIn');
        $this->questionAnswerRepository->shouldNotReceive('findWhereIn');

        $badge = $this->service->buildBadges(self::EVENT_ID, collect([$this->attendee()]), $design)->first();

        $this->assertSame(
            ['Ada Lovelace', 'Speaker pass', '2027-03-04 09:00:00', 'Ask me anything'],
            array_map(fn (BadgeLineDTO $line) => $line->text, $badge->lines),
        );
    }

    public function test_attendee_and_order_answers_resolve_by_question_scope_and_empty_lines_are_dropped(): void
    {
        $design = $this->design(lines: [
            new BadgeLineDTO(BadgeFieldSource::FIRST_NAME, BadgeTextSize::XL),
            new BadgeLineDTO(BadgeFieldSource::QUESTION, BadgeTextSize::M, question_id: 1),
            new BadgeLineDTO(BadgeFieldSource::QUESTION, BadgeTextSize::S, question_id: 2),
            new BadgeLineDTO(BadgeFieldSource::QUESTION, BadgeTextSize::S, question_id: 3),
        ]);

        $this->questionRepository->shouldReceive('findWhereIn')->once()->andReturn(collect([
            $this->question(1, 'PRODUCT', 'SINGLE_LINE_TEXT'),
            $this->question(2, 'ORDER', 'MULTI_SELECT_DROPDOWN'),
            $this->question(3, 'PRODUCT', 'SINGLE_LINE_TEXT'),
        ]));
        $this->questionAnswerRepository->shouldReceive('findWhereIn')
            ->once()
            ->with('order_id', [50], [['question_id', 'in', [1, 2, 3]]])
            ->andReturn(collect([
                $this->answer(1, 50, 99, 'Someone else'),
                $this->answer(1, 50, 11, 'Analytical Engines'),
                $this->answer(2, 50, null, ['Navy', 'Compilers']),
                $this->answer(3, 50, 11, '   '),
            ]));

        $badge = $this->service->buildBadges(self::EVENT_ID, collect([$this->attendee()]), $design)->first();

        $this->assertSame(
            [['FIRST_NAME', 'Ada'], ['QUESTION', 'Analytical Engines'], ['QUESTION', 'Navy, Compilers']],
            array_map(fn (BadgeLineDTO $line) => [$line->source->value, $line->text], $badge->lines),
        );
    }

    public function test_a_blank_name_line_is_kept_for_handwriting(): void
    {
        $badge = $this->service->buildBadges(
            self::EVENT_ID,
            collect([$this->attendee(firstName: '')]),
            $this->design(lines: [new BadgeLineDTO(BadgeFieldSource::FIRST_NAME, BadgeTextSize::XL)]),
        )->first();

        $this->assertCount(1, $badge->lines);
        $this->assertSame('', $badge->lines[0]->text);
    }

    public function test_ribbons_use_the_configured_label_or_fall_back_to_the_ticket_and_accent(): void
    {
        $configured = $this->design(ribbons: [new BadgeRibbonDTO(product_id: 3, label: 'SPEAKER', color: '#b91c1c')]);
        $unlabelled = $this->design(ribbons: [new BadgeRibbonDTO(product_id: 3, label: null, color: '#b91c1c')]);

        $this->assertEquals(
            new BadgeRibbonDTO(product_id: 3, label: 'SPEAKER', color: '#b91c1c'),
            $this->service->buildBadges(self::EVENT_ID, collect([$this->attendee()]), $configured)->first()->ribbon,
        );
        $this->assertEquals(
            new BadgeRibbonDTO(product_id: 3, label: 'Speaker pass', color: '#b91c1c'),
            $this->service->buildBadges(self::EVENT_ID, collect([$this->attendee()]), $unlabelled)->first()->ribbon,
        );
        $this->assertEquals(
            new BadgeRibbonDTO(product_id: 3, label: 'Speaker pass', color: '#123456'),
            $this->service->buildBadges(self::EVENT_ID, collect([$this->attendee()]), $this->design())->first()->ribbon,
        );
    }

    public function test_hidden_ribbon_and_seat_are_left_off(): void
    {
        $design = $this->design();
        $design->show_ribbon = false;
        $design->show_seat = false;

        $badge = $this->service->buildBadges(self::EVENT_ID, collect([$this->attendee()]), $design)->first();

        $this->assertNull($badge->ribbon);
        $this->assertNull($badge->seat_label);
    }

    public function test_print_tracking_is_carried_onto_the_badge(): void
    {
        $attendee = $this->attendee()->setBadgePrintCount(2)->setBadgePrintedAt(null);

        $badge = $this->service->buildBadges(self::EVENT_ID, collect([$attendee]), $this->design())->first();

        $this->assertSame(2, $badge->badge_print_count);
        $this->assertSame(BadgePrintStatus::NEEDS_REPRINT, $badge->print_status);
    }

    private function attendee(string $firstName = 'Ada'): AttendeeDomainObject
    {
        $attendee = (new AttendeeDomainObject)
            ->setId(11)
            ->setPublicId('A-ADA')
            ->setOrderId(50)
            ->setProductId(3)
            ->setEventId(self::EVENT_ID)
            ->setStatus('ACTIVE')
            ->setFirstName($firstName)
            ->setLastName('Lovelace')
            ->setSeatLabel('Row A · 4');

        $attendee->setProduct((new ProductDomainObject)->setId(3)->setTitle('Speaker pass'));
        $attendee->setEventOccurrence((new EventOccurrenceDomainObject)->setId(5)->setStartDate('2027-03-04 09:00:00'));

        return $attendee;
    }

    private function question(int $id, string $belongsTo, string $type): QuestionDomainObject
    {
        return (new QuestionDomainObject)->setId($id)->setBelongsTo($belongsTo)->setType($type)->setTitle('Q'.$id);
    }

    private function answer(int $questionId, int $orderId, ?int $attendeeId, string|array $answer): QuestionAnswerDomainObject
    {
        return (new QuestionAnswerDomainObject)
            ->setQuestionId($questionId)
            ->setOrderId($orderId)
            ->setAttendeeId($attendeeId)
            ->setAnswer($answer);
    }

    /**
     * @param  BadgeLineDTO[]|null  $lines
     * @param  BadgeRibbonDTO[]  $ribbons
     */
    private function design(?array $lines = null, array $ribbons = []): BadgeDesignDTO
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
            lines: $lines ?? [new BadgeLineDTO(BadgeFieldSource::FIRST_NAME, BadgeTextSize::XL)],
            show_logo: true,
            show_qr: true,
            show_ribbon: true,
            show_seat: true,
            show_event_name: true,
            show_sponsors: true,
            footer_text: null,
            ribbons: $ribbons,
            back_mode: BadgeBackMode::NONE,
            back_text: null,
        );
    }
}
