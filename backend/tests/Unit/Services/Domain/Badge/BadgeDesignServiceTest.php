<?php

namespace Tests\Unit\Services\Domain\Badge;

use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeSheet;
use HiEvents\DomainObjects\Enums\BadgeSize;
use HiEvents\DomainObjects\Enums\BadgeTemplate;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\InvalidBadgeDesignException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Badge\BadgeDesignService;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BadgeDesignServiceTest extends TestCase
{
    private const EVENT_ID = 12;

    private MockInterface|EventRepositoryInterface $eventRepository;

    private MockInterface|EventSettingsRepositoryInterface $eventSettingsRepository;

    private MockInterface|QuestionRepositoryInterface $questionRepository;

    private MockInterface|ProductRepositoryInterface $productRepository;

    private BadgeDesignService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);
        $this->questionRepository = Mockery::mock(QuestionRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);

        $this->service = new BadgeDesignService(
            $this->eventRepository,
            $this->eventSettingsRepository,
            $this->questionRepository,
            $this->productRepository,
        );
    }

    public function test_defaults_follow_the_events_currency_brand_and_questions(): void
    {
        $this->givenEvent('USD', (new EventSettingDomainObject)
            ->setId(1)
            ->setTicketDesignSettings(['accent_color' => '#333333'])
            ->setHomepageThemeSettings(['accent' => '#0f766e', 'font_family' => 'Poppins']));
        $this->givenQuestions([
            $this->question(1, 'What is your job title?'),
            $this->question(2, 'Organisation', hidden: true),
            $this->question(3, 'Company / employer'),
            $this->question(4, 'Pronouns'),
        ]);

        $design = $this->service->getDesign(self::EVENT_ID);

        $this->assertSame(BadgeSize::IN_4X3_LANDSCAPE, $design->size);
        $this->assertSame(BadgeSheet::LETTER, $design->sheet);
        $this->assertSame('#0f766e', $design->accent_color);
        $this->assertSame('Poppins', $design->font_family);
        $this->assertSame([3, 1, 4], $design->questionIds());
    }

    public function test_metric_events_default_to_a6_on_a4_with_the_ticket_accent(): void
    {
        $this->givenEvent('EUR', (new EventSettingDomainObject)->setId(1)->setTicketDesignSettings(['accent_color' => '#2563ebff']));
        $this->givenQuestions([]);

        $design = $this->service->getDesign(self::EVENT_ID);

        $this->assertSame(BadgeSize::A6_PORTRAIT, $design->size);
        $this->assertSame(BadgeSheet::A4, $design->sheet);
        $this->assertSame('#2563eb', $design->accent_color);
        $this->assertSame('Inter', $design->font_family);
    }

    public function test_short_and_invalid_accent_colours_become_six_digit_hex(): void
    {
        $this->givenEvent('EUR', (new EventSettingDomainObject)->setId(1)
            ->setTicketDesignSettings(['accent_color' => 'x#abc'])
            ->setHomepageThemeSettings(['accent' => '#0F8']));
        $this->givenQuestions([]);

        $this->assertSame('#00ff88', $this->service->getDesign(self::EVENT_ID)->accent_color);
    }

    public function test_a_saved_design_overrides_the_defaults(): void
    {
        $this->givenEvent('EUR', (new EventSettingDomainObject)->setId(1)->setBadgeDesignSettings([
            'template' => 'BOLD',
            'lines' => [['source' => 'FULL_NAME', 'size' => 'XL']],
        ]));
        $this->givenQuestions([$this->question(3, 'Company')]);

        $design = $this->service->getDesign(self::EVENT_ID);

        $this->assertSame(BadgeTemplate::BOLD, $design->template);
        $this->assertSame([BadgeFieldSource::FULL_NAME], array_map(fn ($line) => $line->source, $design->lines));
        $this->assertSame(BadgeSize::A6_PORTRAIT, $design->size);
    }

    public function test_saving_strips_values_that_do_not_belong_to_a_line_or_size(): void
    {
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn((new EventSettingDomainObject)->setId(1));
        $this->eventSettingsRepository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(function (int $id, array $attributes) {
                $saved = $attributes['badge_design_settings'];

                return $id === 1
                    && $saved['custom_width_mm'] === null
                    && $saved['lines'][0]['text'] === null
                    && $saved['lines'][0]['question_id'] === null
                    && $saved['lines'][1]['text'] === 'Volunteer';
            });

        $this->service->save(self::EVENT_ID, BadgeDesignDTO::from($this->payload([
            'custom_width_mm' => 90,
            'lines' => [
                ['source' => 'FIRST_NAME', 'size' => 'XL', 'text' => 'stray', 'question_id' => 5],
                ['source' => 'CUSTOM_TEXT', 'size' => 'S', 'text' => 'Volunteer'],
            ],
        ])));
    }

    public function test_fold_over_is_dropped_for_single_sided_badges(): void
    {
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn((new EventSettingDomainObject)->setId(1));
        $this->eventSettingsRepository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes['badge_design_settings']['fold_over'] === false);

        $this->service->save(self::EVENT_ID, BadgeDesignDTO::from($this->payload(['fold_over' => true])));
    }

    public function test_fold_over_is_kept_for_double_sided_badges(): void
    {
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn((new EventSettingDomainObject)->setId(1));
        $this->eventSettingsRepository->shouldReceive('updateFromArray')
            ->once()
            ->withArgs(fn (int $id, array $attributes) => $attributes['badge_design_settings']['fold_over'] === true);

        $saved = $this->service->save(self::EVENT_ID, BadgeDesignDTO::from($this->payload([
            'back_mode' => 'INFO',
            'fold_over' => true,
        ])));

        $this->assertTrue($saved->fold_over);
    }

    public function test_saving_rejects_a_question_from_another_event(): void
    {
        $this->questionRepository->shouldReceive('findWhereIn')->andReturn(collect());
        $this->eventSettingsRepository->shouldNotReceive('updateFromArray');

        $this->expectException(InvalidBadgeDesignException::class);

        $this->service->save(self::EVENT_ID, BadgeDesignDTO::from($this->payload([
            'lines' => [['source' => 'QUESTION', 'size' => 'M', 'question_id' => 99]],
        ])));
    }

    public function test_saving_rejects_a_ribbon_for_a_product_outside_the_event(): void
    {
        $this->productRepository->shouldReceive('findWhereIn')->andReturn(collect([(new ProductDomainObject)->setId(5)]));
        $this->eventSettingsRepository->shouldNotReceive('updateFromArray');

        try {
            $this->service->save(self::EVENT_ID, BadgeDesignDTO::from($this->payload([
                'ribbons' => [
                    ['product_id' => 5, 'label' => null, 'color' => '#000000'],
                    ['product_id' => 6, 'label' => null, 'color' => '#000000'],
                ],
            ])));
            $this->fail('Expected the foreign ribbon to be rejected');
        } catch (InvalidBadgeDesignException $exception) {
            $this->assertSame('ribbons.1.product_id', $exception->getField());
        }
    }

    private function givenEvent(string $currency, EventSettingDomainObject $settings): void
    {
        $this->eventRepository->shouldReceive('findById')->with(self::EVENT_ID)
            ->andReturn((new EventDomainObject)->setId(self::EVENT_ID)->setCurrency($currency));
        $this->eventSettingsRepository->shouldReceive('findFirstWhere')->andReturn($settings);
    }

    /**
     * @param  QuestionDomainObject[]  $questions
     */
    private function givenQuestions(array $questions): void
    {
        $this->questionRepository->shouldReceive('findWhere')->andReturn(collect($questions));
    }

    private function question(int $id, string $title, bool $hidden = false): QuestionDomainObject
    {
        return (new QuestionDomainObject)
            ->setId($id)
            ->setTitle($title)
            ->setType('SINGLE_LINE_TEXT')
            ->setIsHidden($hidden)
            ->setOrder($id);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'template' => 'CLASSIC',
            'size' => 'A6_PORTRAIT',
            'custom_width_mm' => null,
            'custom_height_mm' => null,
            'sheet' => 'A4',
            'accent_color' => '#1d4ed8',
            'background_color' => null,
            'text_color' => null,
            'font_family' => 'Inter',
            'text_align' => null,
            'uppercase_names' => false,
            'lines' => [['source' => 'FIRST_NAME', 'size' => 'XL']],
            'show_logo' => true,
            'show_qr' => true,
            'show_ribbon' => true,
            'show_seat' => true,
            'show_event_name' => true,
            'show_sponsors' => true,
            'footer_text' => null,
            'ribbons' => [],
            'back_mode' => 'NONE',
            'back_text' => null,
        ], $overrides);
    }
}
