<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('account_configuration')
            ->whereNotNull('application_fees')
            ->get()
            ->each(function ($row) {
                $fees = json_decode($row->application_fees, true);
                if ($fees && !isset($fees['currency'])) {
                    $fees['currency'] = 'USD';
                    DB::table('account_configuration')
                        ->where('id', $row->id)
                        ->update(['application_fees' => json_encode($fees)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('account_configuration')
            ->whereNotNull('application_fees')
            ->get()
            ->each(function ($row) {
                $fees = json_decode($row->application_fees, true);
                if ($fees && isset($fees['currency'])) {
                    unset($fees['currency']);
                    DB::table('account_configuration')
                        ->where('id', $row->id)
                        ->update(['application_fees' => json_encode($fees)]);
                }
            });
    }
};
