<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('entity_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        // Backfill account_id
        DB::statement("
            UPDATE images
            SET account_id = subquery.account_id
            FROM (
                SELECT i.id,
                       CASE
                           WHEN i.entity_type = 'HiEvents\\DomainObjects\\OrganizerDomainObject'
                               THEN o.account_id
                           WHEN i.entity_type = 'HiEvents\\DomainObjects\\EventDomainObject'
                               THEN e.account_id
                           WHEN i.entity_type = 'HiEvents\\DomainObjects\\UserDomainObject'
                               THEN au.account_id
                           END AS account_id
                FROM images i
                LEFT JOIN organizers o ON i.entity_type = 'HiEvents\\DomainObjects\\OrganizerDomainObject' AND i.entity_id = o.id
                LEFT JOIN events e ON i.entity_type = 'HiEvents\\DomainObjects\\EventDomainObject' AND i.entity_id = e.id
                LEFT JOIN account_users au ON i.entity_type = 'HiEvents\\DomainObjects\\UserDomainObject' AND i.entity_id = au.user_id
            ) AS subquery
            WHERE images.id = subquery.id
        ");
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
