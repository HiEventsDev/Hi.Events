<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->text('referrer_url')->nullable();
            $table->text('landing_page')->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->string('source_type', 20)->nullable();
            $table->jsonb('utm_raw')->nullable();
            $table->timestamps();
            // NO deleted_at - attribution is immutable

            $table->unique('account_id');
            $table->index('utm_source');
            $table->index('utm_campaign');
            $table->index('source_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_attributions');
    }
};
