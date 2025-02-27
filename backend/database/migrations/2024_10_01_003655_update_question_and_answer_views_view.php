<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS question_and_answer_views');

        DB::statement("
            CREATE VIEW question_and_answer_views AS
            SELECT p.id    AS product_id,
                   p.title AS product_title,
                   q.id    AS question_id,
                   q.event_id,
                   q.belongs_to,
                   q.type  AS question_type,
                   a.first_name,
                   a.last_name,
                   a.id    AS attendee_id,
                   qa.order_id,
                   q.title,
                   qa.answer
            FROM question_answers qa
            LEFT JOIN attendees a ON a.id = qa.attendee_id
            LEFT JOIN products p ON p.id = qa.product_id
            JOIN questions q ON q.id = qa.question_id;
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS question_and_answer_views');

        DB::statement("
            CREATE VIEW question_and_answer_views AS
            SELECT q.id AS question_id,
                   q.event_id,
                   q.belongs_to,
                   q.type AS question_type,
                   a.first_name,
                   a.last_name,
                   a.id AS attendee_id,
                   qa.order_id,
                   q.title,
                   qa.answer
            FROM question_answers qa
            LEFT JOIN attendees a ON a.id = qa.attendee_id
            JOIN orders o ON qa.order_id = o.id
            JOIN questions q ON q.id = qa.question_id;
        ");
    }
};
