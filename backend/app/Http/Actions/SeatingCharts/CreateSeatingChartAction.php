<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\SeatingCharts;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\SeatingChartDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\SeatingChartRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateSeatingChartAction extends BaseAction
{
    public function __construct(
        private readonly SeatingChartRepositoryInterface $seatingChartRepository,
    )
    {
    }

    public function __invoke(int $eventId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'layout' => 'nullable|array',
            'total_seats' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'sections' => 'nullable|array',
            'sections.*.name' => 'required|string|max:255',
            'sections.*.label' => 'nullable|string|max:100',
            'sections.*.color' => 'nullable|string|max:7',
            'sections.*.capacity' => 'required|integer|min:1',
            'sections.*.row_count' => 'required|integer|min:1',
            'sections.*.seats_per_row' => 'required|integer|min:1',
            'sections.*.position' => 'nullable|array',
            'sections.*.shape' => 'nullable|string|in:rectangle,arc,circle',
        ]);

        $chart = DB::transaction(function () use ($eventId, $validated) {
            $chart = $this->seatingChartRepository->create([
                'event_id' => $eventId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'layout' => $validated['layout'] ?? null,
                'total_seats' => $validated['total_seats'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['sections'])) {
                $sortOrder = 0;
                foreach ($validated['sections'] as $sectionData) {
                    $section = DB::table('seating_sections')->insertGetId([
                        'seating_chart_id' => $chart->getId(),
                        'name' => $sectionData['name'],
                        'label' => $sectionData['label'] ?? null,
                        'color' => $sectionData['color'] ?? null,
                        'capacity' => $sectionData['capacity'],
                        'row_count' => $sectionData['row_count'],
                        'seats_per_row' => $sectionData['seats_per_row'],
                        'position' => isset($sectionData['position']) ? json_encode($sectionData['position']) : null,
                        'shape' => $sectionData['shape'] ?? 'rectangle',
                        'sort_order' => $sortOrder++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Auto-generate seats for each section
                    $seats = [];
                    for ($row = 1; $row <= $sectionData['row_count']; $row++) {
                        $rowLabel = chr(64 + $row); // A, B, C...
                        for ($seat = 1; $seat <= $sectionData['seats_per_row']; $seat++) {
                            $seats[] = [
                                'section_id' => $section,
                                'chart_id' => $chart->getId(),
                                'row_label' => $rowLabel,
                                'seat_number' => $seat,
                                'label' => $rowLabel . $seat,
                                'status' => 'available',
                                'is_accessible' => false,
                                'is_aisle' => ($seat === 1 || $seat === $sectionData['seats_per_row']),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (!empty($seats)) {
                        DB::table('seats')->insert($seats);
                    }
                }
            }

            return $chart;
        });

        return $this->jsonResponse($chart->toArray(), ResponseCodes::HTTP_CREATED);
    }
}
