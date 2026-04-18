<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('label', 255);
            $table->string('type', 50);
            $table->jsonb('options')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_attribute_definitions');
    }
};
