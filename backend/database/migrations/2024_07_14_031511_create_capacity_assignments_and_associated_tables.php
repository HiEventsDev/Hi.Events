<?php

use HiEvents\DomainObjects\Enums\CapacityAssignmentAppliesTo;
use HiEvents\DomainObjects\Status\CapacityAssignmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('capacity_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('name');
            $table->integer('capacity')->nullable()->default(null);
            $table->integer('used_capacity')->default(0);
            $table->string('applies_to')->default(CapacityAssignmentAppliesTo::EVENT->name);
            $table->string('status')->default(CapacityAssignmentStatus::ACTIVE->name);
            $table->timestamps();
            $table->softDeletes();

            $table->index('event_id');
            $table->index('applies_to');
            $table->index('status');
        });

        Schema::create('ticket_capacity_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('capacity_assignment_id')->constrained('capacity_assignments')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index('ticket_id');
            $table->index('capacity_assignment_id');

            $table->unique(['ticket_id', 'capacity_assignment_id']);
        });

        Schema::table('ticket_prices', function (Blueprint $table) {
            $table->integer('quantity_available')->default(null)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_prices', function (Blueprint $table) {
            $table->dropColumn('quantity_available');
        });

        Schema::dropIfExists('ticket_capacity_assignments');
        Schema::dropIfExists('capacity_assignments');
    }
};
