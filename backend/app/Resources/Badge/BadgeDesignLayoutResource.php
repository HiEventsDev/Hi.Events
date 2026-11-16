<?php

declare(strict_types=1);

namespace HiEvents\Resources\Badge;

use HiEvents\Services\Domain\Badge\DTO\BadgeDesignDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BadgeDesignDTO
 */
class BadgeDesignLayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var 'CLASSIC'|'BANNER'|'MINIMAL'|'BOLD'|'SIDEBAR'|'ARTWORK' */
            'template' => $this->template->value,
            /** @var 'A6_PORTRAIT'|'A7_PORTRAIT'|'IN_4X3_LANDSCAPE'|'IN_3X4_PORTRAIT'|'IN_4X6_PORTRAIT'|'CR80_PORTRAIT'|'CR80_LANDSCAPE'|'LABEL_62X100'|'CUSTOM' */
            'size' => $this->size->value,
            'custom_width_mm' => $this->custom_width_mm,
            'custom_height_mm' => $this->custom_height_mm,
            /** @var 'A4'|'LETTER'|'NONE' */
            'sheet' => $this->sheet->value,
            'accent_color' => $this->accent_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'font_family' => $this->font_family,
            /** @var 'LEFT'|'CENTER'|null */
            'text_align' => $this->text_align,
            'uppercase_names' => $this->uppercase_names,
            'show_logo' => $this->show_logo,
            'show_qr' => $this->show_qr,
            'show_ribbon' => $this->show_ribbon,
            'show_seat' => $this->show_seat,
            'show_event_name' => $this->show_event_name,
            'show_sponsors' => $this->show_sponsors,
            'footer_text' => $this->footer_text,
            /** @var 'NONE'|'SAME'|'INFO' */
            'back_mode' => $this->back_mode->value,
            'back_text' => $this->back_text,
            'fold_over' => $this->fold_over,
        ];
    }
}
