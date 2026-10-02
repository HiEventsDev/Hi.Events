<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Resources;

use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use Illuminate\Support\Collection;

trait SerializesSeatMapLayout
{
    private function layoutForResponse(array $layout): object
    {
        return json_decode(json_encode($layout, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
    }

    private function previewLayoutForResponse(array $layout): object
    {
        $layout['areas'] = array_map(static function (array $area) {
            $area['elements'] = array_map(static function (array $element) {
                if (array_key_exists('seats', $element)) {
                    $element['seats'] = [];
                }

                return $element;
            }, $area['elements'] ?? []);

            return $area;
        }, $layout['areas'] ?? []);

        return $this->layoutForResponse($layout);
    }

    /**
     * @param  Collection<int, EventSeatMapBandProductDomainObject>|null  $links
     */
    private function bandProductsForResponse(?Collection $links, bool $includePriceAdjustments): array
    {
        return ($links ?? new Collection)
            ->groupBy(fn (EventSeatMapBandProductDomainObject $link) => $link->getBandKey())
            ->map(fn (Collection $bandLinks, string $bandKey) => [
                'band_key' => $bandKey,
                'products' => $bandLinks->map(fn (EventSeatMapBandProductDomainObject $link) => array_merge(
                    ['product_id' => $link->getProductId()],
                    $includePriceAdjustments ? ['price_adjustment' => $link->getPriceAdjustment()] : [],
                ))->values()->all(),
            ])
            ->values()
            ->all();
    }
}
