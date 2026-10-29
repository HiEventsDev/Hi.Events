<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\BadgeBackMode;
use HiEvents\DomainObjects\Enums\BadgeFieldSource;
use HiEvents\DomainObjects\Enums\BadgeSheet;
use HiEvents\DomainObjects\Enums\BadgeSize;
use HiEvents\DomainObjects\Enums\BadgeTemplate;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class BadgeDesignDTO extends BaseDataObject
{
    /**
     * @param  BadgeLineDTO[]  $lines
     * @param  BadgeRibbonDTO[]  $ribbons
     */
    public function __construct(
        public BadgeTemplate $template,
        public BadgeSize $size,
        public ?int $custom_width_mm,
        public ?int $custom_height_mm,
        public BadgeSheet $sheet,
        public string $accent_color,
        public ?string $background_color,
        public ?string $text_color,
        public string $font_family,
        public ?string $text_align,
        public bool $uppercase_names,
        #[DataCollectionOf(BadgeLineDTO::class)]
        public array $lines,
        public bool $show_logo,
        public bool $show_qr,
        public bool $show_ribbon,
        public bool $show_seat,
        public bool $show_event_name,
        public bool $show_sponsors,
        public ?string $footer_text,
        #[DataCollectionOf(BadgeRibbonDTO::class)]
        public array $ribbons,
        public BadgeBackMode $back_mode,
        public ?string $back_text,
        public bool $fold_over = false,
    ) {}

    /**
     * @return int[]
     */
    public function questionIds(): array
    {
        return collect($this->lines)
            ->filter(fn (BadgeLineDTO $line) => $line->source === BadgeFieldSource::QUESTION && $line->question_id !== null)
            ->map(fn (BadgeLineDTO $line) => $line->question_id)
            ->unique()
            ->values()
            ->all();
    }

    public function ribbonForProduct(int $productId): ?BadgeRibbonDTO
    {
        return collect($this->ribbons)->first(fn (BadgeRibbonDTO $ribbon) => $ribbon->product_id === $productId);
    }
}
