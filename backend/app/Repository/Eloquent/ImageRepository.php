<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\ImageDomainObjectAbstract;
use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Image;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<ImageDomainObject>
 */
class ImageRepository extends BaseRepository implements ImageRepositoryInterface
{
    protected function getModel(): string
    {
        return Image::class;
    }

    public function getDomainObject(): string
    {
        return ImageDomainObject::class;
    }

    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [ImageDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder->where(ImageDomainObjectAbstract::FILENAME, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            column: $this->validateSortColumn($params->sort_by, ImageDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, ImageDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }
}
