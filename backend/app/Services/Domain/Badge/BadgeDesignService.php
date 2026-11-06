<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\Enums\BadgeBackMode;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeSheet;
use HiEvents\DomainObjects\Enums\BadgeSize;
use HiEvents\DomainObjects\Enums\BadgeTemplate;
use HiEvents\DomainObjects\Enums\BadgeTextSize;
use HiEvents\DomainObjects\Enums\HomepageFontFamily;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\Enums\QuestionTypeEnum;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Exceptions\InvalidBadgeDesignException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeLineDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeRibbonDTO;
use Illuminate\Support\Collection;

class BadgeDesignService
{
    private const FALLBACK_ACCENT = '#1f2937';

    private const UNSET_TICKET_ACCENT = '#333333';

    private const LETTER_CURRENCIES = ['USD', 'CAD'];

    private const COMPANY_PATTERN = '/\b(company|organi[sz]ation|organisation|employer|business|firm|firma|empresa|entreprise|soci[ée]t[ée]|unternehmen|azienda|bedrijf|organisatie)\b/iu';

    private const JOB_TITLE_PATTERN = '/(job\s*title|\brole\b|\bposition\b|\bdesignation\b|\boccupation\b|\bberuf\b|\bfunktion\b|\bposte\b|\bcargo\b|\bpuesto\b|\bruolo\b|\bfunctie\b)/iu';

    private const PRONOUNS_PATTERN = '/pronoun|pronom|pronomen/iu';

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly QuestionRepositoryInterface $questionRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    public function getDesign(int $eventId): BadgeDesignDTO
    {
        $settings = $this->findSettings($eventId);
        $defaults = $this->buildDefaults($this->eventRepository->findById($eventId), $settings);
        $stored = $settings->getBadgeDesignSettings();

        if (empty($stored)) {
            return $defaults;
        }

        return BadgeDesignDTO::from(array_replace($defaults->toArray(), $stored));
    }

    public function isCustomized(int $eventId): bool
    {
        return ! empty($this->findSettings($eventId)->getBadgeDesignSettings());
    }

    /**
     * @throws InvalidBadgeDesignException
     */
    public function save(int $eventId, BadgeDesignDTO $design): BadgeDesignDTO
    {
        $design->lines = array_map(fn (BadgeLineDTO $line) => new BadgeLineDTO(
            source: $line->source,
            size: $line->size,
            question_id: $line->source === BadgeFieldSource::QUESTION ? $line->question_id : null,
            text: $line->source === BadgeFieldSource::CUSTOM_TEXT ? $line->text : null,
        ), array_values($design->lines));

        if ($design->size !== BadgeSize::CUSTOM) {
            $design->custom_width_mm = null;
            $design->custom_height_mm = null;
        }

        if ($design->back_mode === BadgeBackMode::NONE) {
            $design->fold_over = false;
        }

        $this->assertQuestionsBelongToEvent($eventId, $design);
        $this->assertRibbonProductsBelongToEvent($eventId, $design);

        $this->eventSettingsRepository->updateFromArray(
            id: $this->findSettings($eventId)->getId(),
            attributes: [EventSettingDomainObjectAbstract::BADGE_DESIGN_SETTINGS => $design->toArray()],
        );

        return $design;
    }

    /**
     * @return Collection<int, QuestionDomainObject>
     */
    public function getBindableQuestions(int $eventId): Collection
    {
        return $this->questionRepository
            ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $eventId])
            ->filter(fn (QuestionDomainObject $question) => $question->getType() !== QuestionTypeEnum::ADDRESS->name)
            ->sortBy(fn (QuestionDomainObject $question) => $question->getOrder())
            ->values();
    }

    private function buildDefaults(EventDomainObject $event, EventSettingDomainObject $settings): BadgeDesignDTO
    {
        $usesLetter = in_array(strtoupper((string) $event->getCurrency()), self::LETTER_CURRENCIES, true);

        return new BadgeDesignDTO(
            template: BadgeTemplate::CLASSIC,
            size: $usesLetter ? BadgeSize::IN_4X3_LANDSCAPE : BadgeSize::A6_PORTRAIT,
            custom_width_mm: null,
            custom_height_mm: null,
            sheet: $usesLetter ? BadgeSheet::LETTER : BadgeSheet::A4,
            accent_color: $this->defaultAccent($settings),
            background_color: null,
            text_color: null,
            font_family: $this->defaultFont($settings),
            text_align: null,
            uppercase_names: false,
            lines: $this->defaultLines($event->getId()),
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
            fold_over: false,
        );
    }

    /**
     * @return BadgeLineDTO[]
     */
    private function defaultLines(int $eventId): array
    {
        $lines = [
            new BadgeLineDTO(source: BadgeFieldSource::FIRST_NAME, size: BadgeTextSize::XL),
            new BadgeLineDTO(source: BadgeFieldSource::LAST_NAME, size: BadgeTextSize::L),
        ];

        $questions = $this->getBindableQuestions($eventId)
            ->filter(fn (QuestionDomainObject $question) => ! $question->getIsHidden());

        foreach ([[self::COMPANY_PATTERN, BadgeTextSize::M], [self::JOB_TITLE_PATTERN, BadgeTextSize::S], [self::PRONOUNS_PATTERN, BadgeTextSize::S]] as [$pattern, $size]) {
            $question = $questions->first(fn (QuestionDomainObject $question) => preg_match($pattern, $question->getTitle()) === 1);

            if ($question !== null) {
                $lines[] = new BadgeLineDTO(source: BadgeFieldSource::QUESTION, size: $size, question_id: $question->getId());
                $questions = $questions->reject(fn (QuestionDomainObject $candidate) => $candidate->getId() === $question->getId());
            }
        }

        return $lines;
    }

    private function defaultAccent(EventSettingDomainObject $settings): string
    {
        $ticketAccent = $this->toSixDigitHex($settings->getTicketDesignSettings()['accent_color'] ?? null);
        if ($ticketAccent !== null && $ticketAccent !== self::UNSET_TICKET_ACCENT) {
            return $ticketAccent;
        }

        return $this->toSixDigitHex($settings->getHomepageThemeSettings()['accent'] ?? null) ?? self::FALLBACK_ACCENT;
    }

    private function toSixDigitHex(mixed $color): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        $color = strtolower(trim($color));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])[0-9a-f]?$/', $color, $short) === 1) {
            return '#'.$short[1].$short[1].$short[2].$short[2].$short[3].$short[3];
        }

        return preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/', $color) === 1 ? substr($color, 0, 7) : null;
    }

    private function defaultFont(EventSettingDomainObject $settings): string
    {
        $font = $settings->getHomepageThemeSettings()['font_family'] ?? null;

        return HomepageFontFamily::tryFrom((string) $font)?->value ?? HomepageFontFamily::Inter->value;
    }

    private function findSettings(int $eventId): EventSettingDomainObject
    {
        /** @var EventSettingDomainObject */
        return $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }

    /**
     * @throws InvalidBadgeDesignException
     */
    private function assertQuestionsBelongToEvent(int $eventId, BadgeDesignDTO $design): void
    {
        $questionIds = $design->questionIds();
        if ($questionIds === []) {
            return;
        }

        $validIds = $this->questionRepository
            ->findWhereIn(QuestionDomainObjectAbstract::ID, $questionIds, [QuestionDomainObjectAbstract::EVENT_ID => $eventId])
            ->filter(fn (QuestionDomainObject $question) => $question->getType() !== QuestionTypeEnum::ADDRESS->name)
            ->map(fn (QuestionDomainObject $question) => $question->getId())
            ->all();

        foreach ($design->lines as $index => $line) {
            if ($line->source === BadgeFieldSource::QUESTION && ! in_array($line->question_id, $validIds, true)) {
                throw new InvalidBadgeDesignException(
                    "lines.$index.question_id",
                    __('Choose one of this event\'s questions for this line.'),
                );
            }
        }
    }

    /**
     * @throws InvalidBadgeDesignException
     */
    private function assertRibbonProductsBelongToEvent(int $eventId, BadgeDesignDTO $design): void
    {
        $productIds = collect($design->ribbons)->map(fn (BadgeRibbonDTO $ribbon) => $ribbon->product_id)->all();
        if ($productIds === []) {
            return;
        }

        $validIds = $this->productRepository
            ->findWhereIn(ProductDomainObjectAbstract::ID, $productIds, [
                ProductDomainObjectAbstract::EVENT_ID => $eventId,
                ProductDomainObjectAbstract::PRODUCT_TYPE => ProductType::TICKET->name,
            ])
            ->map(fn ($product) => $product->getId())
            ->all();

        foreach ($design->ribbons as $index => $ribbon) {
            if (! in_array($ribbon->product_id, $validIds, true)) {
                throw new InvalidBadgeDesignException(
                    "ribbons.$index.product_id",
                    __('Choose one of this event\'s tickets for this ribbon.'),
                );
            }
        }
    }
}
