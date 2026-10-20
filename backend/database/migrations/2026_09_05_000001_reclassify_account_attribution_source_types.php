<?php

declare(strict_types=1);

use HiEvents\Services\Domain\Account\AttributionSourceClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $classifier = app(AttributionSourceClassifier::class);

        DB::table('account_attributions')
            ->select(['id', 'utm_medium', 'referrer_url', 'gclid', 'fbclid', 'source_type', 'utm_raw'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($classifier) {
                foreach ($rows as $row) {
                    $sourceType = $classifier->classify(
                        utmMedium: $row->utm_medium,
                        referrerUrl: $row->referrer_url,
                        gclid: $row->gclid,
                        fbclid: $row->fbclid,
                        utmRaw: $row->utm_raw === null ? null : json_decode($row->utm_raw, true),
                    )->value;

                    if ($sourceType !== $row->source_type) {
                        DB::table('account_attributions')
                            ->where('id', $row->id)
                            ->update(['source_type' => $sourceType]);
                    }
                }
            });
    }

    public function down(): void {}
};
