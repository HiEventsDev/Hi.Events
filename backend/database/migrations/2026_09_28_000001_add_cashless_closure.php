<?php

use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_settings', function (Blueprint $table) {
            $table->timestamp('cashless_closed_at')->nullable();
        });

        $this->replaceTransactionTypeConstraint(CashlessTransactionType::valuesArray());
    }

    public function down(): void
    {
        DB::table('cashless_transactions')->where('type', CashlessTransactionType::CLOSURE->value)->delete();

        $this->replaceTransactionTypeConstraint(array_values(array_diff(
            CashlessTransactionType::valuesArray(),
            [CashlessTransactionType::CLOSURE->value],
        )));

        Schema::table('event_settings', function (Blueprint $table) {
            $table->dropColumn('cashless_closed_at');
        });
    }

    private function replaceTransactionTypeConstraint(array $types): void
    {
        $list = implode(', ', array_map(static fn (string $type) => "'".$type."'", $types));

        DB::statement('ALTER TABLE cashless_transactions DROP CONSTRAINT cashless_transactions_type_check');
        DB::statement("ALTER TABLE cashless_transactions ADD CONSTRAINT cashless_transactions_type_check CHECK (type::text IN ($list))");
    }
};
