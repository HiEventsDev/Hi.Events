<?php

use HiEvents\Helper\IdHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()
                ->after('organizer_configuration_id')
                ->constrained('locations')
                ->nullOnDelete();
            $table->index('location_id');
        });

        DB::table('organizer_settings')
            ->whereNotNull('location_details')
            ->orderBy('id')
            ->chunkById(200, function ($settings) {
                foreach ($settings as $row) {
                    $address = $this->normaliseAddress($row->location_details);
                    if ($address === null) {
                        continue;
                    }

                    $organizer = DB::table('organizers')->find($row->organizer_id);
                    if (! $organizer || $organizer->location_id !== null) {
                        continue;
                    }

                    $locationId = DB::table('locations')->insertGetId([
                        'short_id' => IdHelper::shortId(IdHelper::LOCATION_PREFIX),
                        'account_id' => $organizer->account_id,
                        'organizer_id' => $organizer->id,
                        'name' => $address['venue_name'] ?? null,
                        'structured_address' => json_encode($address, JSON_UNESCAPED_UNICODE),
                        'latitude' => null,
                        'longitude' => null,
                        'provider' => null,
                        'provider_place_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('organizers')
                        ->where('id', $organizer->id)
                        ->update(['location_id' => $locationId]);
                }
            });

        // Legacy organizer_settings.location_details is intentionally retained
        // here. Dropping it is deferred to a follow-up migration once we've
        // confirmed the new locations table is the only read path.
    }

    public function down(): void
    {
        Schema::table('organizers', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });
    }

    private function normaliseAddress(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        foreach (['venue_name', 'address_line_1', 'city', 'state_or_region', 'zip_or_postal_code', 'country'] as $key) {
            if (! empty($decoded[$key])) {
                return $decoded;
            }
        }

        return null;
    }
};
