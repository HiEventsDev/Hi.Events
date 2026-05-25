<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds the event_location_id pivot to events and event_occurrences, then
 * backfills event_locations / locations rows from the legacy address
 * columns. Backfill logic is inlined so the migration has zero dependency
 * on application namespaces — it must keep working against fresh installs
 * and delayed deploys even after future domain-code refactors.
 *
 * Legacy columns (events.location_details, event_settings.{location_details,
 * online_event_connection_details, is_online_event}) are retained; a
 * follow-up migration drops them once read paths have moved over.
 *
 * Idempotent: events with event_location_id already set are skipped.
 */
return new class extends Migration
{
    private const TYPE_IN_PERSON = 'IN_PERSON';

    private const TYPE_ONLINE = 'ONLINE';

    private const LOCATION_SHORT_ID_PREFIX = 'loc';

    private const EVENT_LOCATION_SHORT_ID_PREFIX = 'el';

    private const SHORT_ID_RANDOM_LENGTH = 13;

    private ?\HTMLPurifier $purifier = null;

    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('event_location_id')->nullable()->after('organizer_id')->constrained('event_locations')->nullOnDelete();
            $table->index('event_location_id');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->foreignId('event_location_id')->nullable()->after('event_id')->constrained('event_locations')->nullOnDelete();
            $table->index('event_location_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropForeign(['event_location_id']);
            $table->dropIndex(['event_location_id']);
            $table->dropColumn('event_location_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['event_location_id']);
            $table->dropIndex(['event_location_id']);
            $table->dropColumn('event_location_id');
        });
    }

    /**
     * Public so feature tests can invoke the backfill against pretend
     * pre-migration data without rerunning the schema changes.
     */
    public function backfill(): int
    {
        $backfilled = 0;

        DB::table('events')
            ->whereNull('event_location_id')
            ->orderBy('id')
            ->chunkById(200, function ($events) use (&$backfilled) {
                foreach ($events as $event) {
                    if ($this->backfillEvent($event)) {
                        $backfilled++;
                    }
                }
            });

        return $backfilled;
    }

    private function backfillEvent(stdClass $event): bool
    {
        $settings = DB::table('event_settings')
            ->where('event_id', $event->id)
            ->first();

        if ($this->isOnlineEvent($settings)) {
            // Re-purify on the way through: the legacy column was sanitised
            // when written, but online_event_connection_details is rendered
            // post-checkout with dangerouslySetInnerHTML, so we don't want
            // to trust historical data that may predate today's allowlist.
            $eventLocationId = $this->createOnlineEventLocation(
                eventId: (int) $event->id,
                onlineDetails: $this->purify($settings->online_event_connection_details ?? null),
            );
            $this->linkEvent((int) $event->id, $eventLocationId);

            return true;
        }

        $address = $this->extractAddress($settings, $event);
        if ($address === null) {
            return false;
        }

        $locationId = $this->createLocation(
            accountId: (int) $event->account_id,
            organizerId: (int) $event->organizer_id,
            address: $address,
        );
        $eventLocationId = $this->createInPersonEventLocation(
            eventId: (int) $event->id,
            locationId: $locationId,
        );
        $this->linkEvent((int) $event->id, $eventLocationId);

        return true;
    }

    private function isOnlineEvent(?stdClass $settings): bool
    {
        return $settings !== null && (bool) ($settings->is_online_event ?? false);
    }

    private function extractAddress(?stdClass $settings, stdClass $event): ?array
    {
        $candidates = [
            $settings->location_details ?? null,
            $event->location_details ?? null,
        ];

        foreach ($candidates as $raw) {
            $address = $this->normaliseAddress($raw);
            if ($address !== null) {
                return $address;
            }
        }

        return null;
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

    private function createLocation(int $accountId, int $organizerId, array $address): int
    {
        $now = now();

        return DB::table('locations')->insertGetId([
            'short_id' => $this->shortId(self::LOCATION_SHORT_ID_PREFIX),
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'name' => $address['venue_name'] ?? null,
            'structured_address' => json_encode($address, JSON_UNESCAPED_UNICODE),
            'latitude' => null,
            'longitude' => null,
            'provider' => null,
            'provider_place_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createInPersonEventLocation(int $eventId, int $locationId): int
    {
        $now = now();

        return DB::table('event_locations')->insertGetId([
            'short_id' => $this->shortId(self::EVENT_LOCATION_SHORT_ID_PREFIX),
            'event_id' => $eventId,
            'type' => self::TYPE_IN_PERSON,
            'location_id' => $locationId,
            'online_event_connection_details' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createOnlineEventLocation(int $eventId, ?string $onlineDetails): int
    {
        $now = now();

        return DB::table('event_locations')->insertGetId([
            'short_id' => $this->shortId(self::EVENT_LOCATION_SHORT_ID_PREFIX),
            'event_id' => $eventId,
            'type' => self::TYPE_ONLINE,
            'location_id' => null,
            'online_event_connection_details' => $onlineDetails,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function linkEvent(int $eventId, int $eventLocationId): void
    {
        DB::table('events')
            ->where('id', $eventId)
            ->update(['event_location_id' => $eventLocationId]);
    }

    private function shortId(string $prefix): string
    {
        return sprintf('%s_%s', $prefix, Str::random(self::SHORT_ID_RANDOM_LENGTH));
    }

    private function purify(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return $this->purifier()->purify($html);
    }

    private function purifier(): \HTMLPurifier
    {
        return $this->purifier ??= new \HTMLPurifier(\HTMLPurifier_Config::createDefault());
    }
};
