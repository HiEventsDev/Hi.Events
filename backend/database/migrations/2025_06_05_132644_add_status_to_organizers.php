<?php

use HiEvents\DomainObjects\Status\OrganizerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizers', static function (Blueprint $table) {
            $table->string('status', 20)->default(OrganizerStatus::DRAFT->name);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('organizers', static function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
