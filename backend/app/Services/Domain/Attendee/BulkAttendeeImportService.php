<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Attendee;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BulkAttendeeImportService
{
    public function parseAndValidate(string $csvContent, int $eventId): array
    {
        $lines = array_filter(explode("\n", trim($csvContent)));
        if (count($lines) < 2) {
            return ['errors' => ['CSV must contain a header row and at least one data row.'], 'valid' => []];
        }

        $headers = array_map('trim', str_getcsv(array_shift($lines)));
        $requiredHeaders = ['first_name', 'last_name', 'email'];
        $missingHeaders = array_diff($requiredHeaders, $headers);

        if (!empty($missingHeaders)) {
            return [
                'errors' => ['Missing required columns: ' . implode(', ', $missingHeaders)],
                'valid' => [],
            ];
        }

        $valid = [];
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            $data = str_getcsv(trim($line));
            if (count($data) !== count($headers)) {
                $errors[] = "Row " . ($lineNum + 2) . ": Column count mismatch.";
                continue;
            }

            $row = array_combine($headers, $data);

            $validator = Validator::make($row, [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'product_id' => 'nullable|integer',
                'product_price_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                $rowErrors = collect($validator->errors()->all())->implode('; ');
                $errors[] = "Row " . ($lineNum + 2) . ": " . $rowErrors;
                continue;
            }

            $valid[] = $row;
        }

        return ['errors' => $errors, 'valid' => $valid];
    }

    public function import(array $validRows, int $eventId, int $accountId, bool $sendTickets = false): array
    {
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($validRows, $eventId, $accountId, &$imported, &$skipped) {
            foreach ($validRows as $row) {
                $shortId = Str::random(12);
                $publicId = Str::uuid()->toString();

                $productId = !empty($row['product_id']) ? (int)$row['product_id'] : null;
                $productPriceId = !empty($row['product_price_id']) ? (int)$row['product_price_id'] : null;

                // Check for duplicate email + event combo
                $existing = DB::table('attendees')
                    ->where('event_id', $eventId)
                    ->where('email', $row['email'])
                    ->when($productId, fn($q) => $q->where('product_id', $productId))
                    ->exists();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                DB::table('attendees')->insert([
                    'event_id' => $eventId,
                    'product_id' => $productId,
                    'product_price_id' => $productPriceId,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'short_id' => $shortId,
                    'public_id' => $publicId,
                    'status' => 'ACTIVE',
                    'checked_in_at' => null,
                    'locale' => $row['locale'] ?? 'en',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'total_rows' => count($validRows),
        ];
    }
}
