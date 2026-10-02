<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use Illuminate\Support\Collection;

class BoxOfficeCatalogueDTO extends BaseDataObject
{
    public function __construct(
        public Collection $products,
        public Collection $questions,
    ) {}
}
