<?php

declare(strict_types=1);

namespace HiEvents\Resources\Badge;

use HiEvents\Services\Application\Handlers\Badge\DTO\BadgeDesignResultDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeLineDTO;
use HiEvents\Services\Domain\Badge\DTO\BadgeRibbonDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BadgeDesignResultDTO
 */
class BadgeDesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new BadgeDesignLayoutResource($this->design))->toArray($request),
            'lines' => collect($this->design->lines)->map(fn (BadgeLineDTO $line) => [
                /** @var 'FIRST_NAME'|'LAST_NAME'|'FULL_NAME'|'TICKET_TYPE'|'QUESTION'|'OCCURRENCE_DATE'|'CUSTOM_TEXT' */
                'source' => $line->source->value,
                /** @var 'XL'|'L'|'M'|'S' */
                'size' => $line->size->value,
                'question_id' => $line->question_id,
                'text' => $line->text,
            ])->values()->all(),
            'ribbons' => collect($this->design->ribbons)->map(fn (BadgeRibbonDTO $ribbon) => [
                'product_id' => $ribbon->product_id,
                'label' => $ribbon->label,
                'color' => $ribbon->color,
            ])->values()->all(),
            'is_customized' => $this->is_customized,
        ];
    }
}
