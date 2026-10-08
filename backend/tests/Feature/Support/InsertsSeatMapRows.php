<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use Illuminate\Support\Facades\DB;

trait InsertsSeatMapRows
{
    private function seatMapFixture(string $template): array
    {
        return json_decode(
            file_get_contents(base_path("tests/Fixtures/seating/$template.json")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, int[]>  $bandProducts
     */
    private function insertEventSeatMap(array $layout, array $bandProducts = []): int
    {
        $eventSeatMapId = DB::table('event_seat_maps')->insertGetId([
            'event_id' => $this->eventId,
            'layout' => json_encode($layout),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($bandProducts as $bandKey => $productIds) {
            foreach ($productIds as $productId) {
                DB::table('event_seat_map_band_products')->insert([
                    'event_seat_map_id' => $eventSeatMapId,
                    'band_key' => $bandKey,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $eventSeatMapId;
    }

    private function updateEventSeatMap(int $eventSeatMapId, array $values): void
    {
        $eventId = DB::table('event_seat_maps')->where('id', $eventSeatMapId)->value('event_id');
        DB::table('event_seat_maps')->where('id', $eventSeatMapId)->update($values);
        app(EventSeatMapLookupService::class)->forget($eventId);
    }

    private function deleteEventSeatMapOf(int $eventId): void
    {
        DB::table('event_seat_maps')->where('event_id', $eventId)->delete();
        app(EventSeatMapLookupService::class)->forget($eventId);
    }
}
