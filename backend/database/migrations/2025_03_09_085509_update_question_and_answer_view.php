<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS question_and_answer_views');

        DB::statement("
            CREATE VIEW question_and_answer_views AS
            SELECT qa.id        AS question_answer_id,
                   p.id         AS product_id,
                   p.title      AS product_title,
                   q.id         AS question_id,
                   q.event_id,
                   q.belongs_to,
                   q.type       AS question_type,
                   a.first_name,
                   a.last_name,
                   a.public_id  AS attendee_public_id,
                   a.email      AS attendee_email,
                   a.id         AS attendee_id,
                   o.id         AS order_id,
                   o.public_id  AS order_public_id,
                   o.first_name AS order_first_name,
                   o.last_name  AS order_last_name,
                   o.email      AS order_email,
                   q.title,
                   q.description AS question_description,
                   q.required AS question_required,
                   q.options AS question_options,
                   qa.answer
            FROM question_answers qa
                     LEFT JOIN attendees a ON a.id = qa.attendee_id
                     LEFT JOIN products p ON p.id = qa.product_id
                     LEFT JOIN orders o ON qa.order_id = o.id
                     JOIN questions q ON q.id = qa.question_id
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS question_and_answer_views');

        DB::statement("
            CREATE VIEW question_and_answer_views AS
            SELECT
                   qa.id   AS question_answer_id,
                   p.id    AS product_id,
                   p.title AS product_title,
                   q.id    AS question_id,
                   q.event_id,
                   q.belongs_to,
                   q.type  AS question_type,
                   a.first_name,
                   a.last_name,
                   a.id    AS attendee_id,
                   a.public_id AS attendee_public_id,
                   qa.order_id,
                   q.title,
                   q.description AS question_description,
                   q.required AS question_required,
                   q.options AS question_options,
                   qa.answer
            FROM question_answers qa
            LEFT JOIN attendees a ON a.id = qa.attendee_id
            LEFT JOIN products p ON p.id = qa.product_id
            JOIN questions q ON q.id = qa.question_id;
        ");
    }
};
