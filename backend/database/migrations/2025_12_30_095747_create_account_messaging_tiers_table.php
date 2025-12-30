<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_messaging_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->integer('max_messages_per_24h');
            $table->integer('max_recipients_per_message');
            $table->boolean('links_allowed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('account_messaging_tiers')->insert([
            [
                'name' => 'Untrusted',
                'max_messages_per_24h' => 3,
                'max_recipients_per_message' => 100,
                'links_allowed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Trusted',
                'max_messages_per_24h' => 10,
                'max_recipients_per_message' => 5000,
                'links_allowed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premium',
                'max_messages_per_24h' => 50,
                'max_recipients_per_message' => 50000,
                'links_allowed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_messaging_tiers');
    }
};
