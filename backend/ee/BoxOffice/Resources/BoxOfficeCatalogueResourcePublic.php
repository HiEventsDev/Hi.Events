<?php

namespace HiEvents\Enterprise\BoxOffice\Resources;

use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeCatalogueDTO;
use HiEvents\Resources\Question\QuestionResourcePublic;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoxOfficeCatalogueDTO
 */
class BoxOfficeCatalogueResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'products' => BoxOfficeProductResourcePublic::collection($this->products),
            'questions' => QuestionResourcePublic::collection($this->questions),
        ];
    }
}
