<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Nette\NotImplementedException;

class InitialDb extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(file_get_contents(__DIR__ . '/schema.mysql.sql'));
            return;
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(file_get_contents(__DIR__ . '/extensions.sql'));
            DB::unprepared(file_get_contents(__DIR__ . '/schema.sql'));
            return;
        }
        throw new NotImplementedException("Unsupported database driver: " . DB::getDriverName() . ". Only mysql and psql are supported.");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
