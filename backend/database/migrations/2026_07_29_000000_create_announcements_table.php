<?php

use HiEvents\DomainObjects\Enums\AnnouncementTargetType;
use HiEvents\DomainObjects\Status\AnnouncementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('status')->default(AnnouncementStatus::DRAFT->name);
            $table->string('display_type');
            $table->string('emoji')->nullable();
            $table->string('target_type')->default(AnnouncementTargetType::ALL->name);
            $table->json('target_account_ids')->nullable();
            $table->json('target_user_ids')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
