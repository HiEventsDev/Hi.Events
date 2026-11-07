<?php

namespace HiEvents\Mcp\Tools\Support;

class SalesPresenter
{
    public static function attendee(array $attendee, string $timezone): array
    {
        $checkIns = collect($attendee['check_ins'] ?? []);

        return array_filter([
            'id' => $attendee['id'],
            'reference' => $attendee['public_id'] ?? null,
            'first_name' => $attendee['first_name'] ?? null,
            'last_name' => $attendee['last_name'] ?? null,
            'email' => $attendee['email'] ?? null,
            'status' => $attendee['status'] ?? null,
            'ticket_id' => $attendee['product_id'] ?? null,
            'ticket_title' => $attendee['product']['title'] ?? null,
            'tier_id' => $attendee['product_price_id'] ?? null,
            'date_id' => $attendee['event_occurrence_id'] ?? null,
            'date' => McpPresenter::localDate($attendee['event_occurrence']['start_date'] ?? null, $timezone),
            'seat' => $attendee['seat_label'] ?? null,
            'order_id' => $attendee['order_id'] ?? null,
            'checked_in' => array_key_exists('check_ins', $attendee) ? $checkIns->isNotEmpty() : null,
            'checked_in_at' => McpPresenter::localDate($checkIns->min('created_at'), $timezone),
            'notes' => $attendee['notes'] ?? null,
            'answers' => array_key_exists('question_answers', $attendee) ? self::answers($attendee['question_answers']) : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public static function order(array $order, string $timezone): array
    {
        return array_filter([
            'id' => $order['id'],
            'reference' => $order['public_id'] ?? null,
            'status' => $order['status'] ?? null,
            'payment_status' => $order['payment_status'] ?? null,
            'refund_status' => $order['refund_status'] ?? null,
            'first_name' => $order['first_name'] ?? null,
            'last_name' => $order['last_name'] ?? null,
            'email' => $order['email'] ?? null,
            'created_at' => McpPresenter::localDate($order['created_at'] ?? null, $timezone),
            'currency' => $order['currency'] ?? null,
            'total' => $order['total_gross'] ?? null,
            'total_tax' => $order['total_tax'] ?? null,
            'total_fees' => $order['total_fee'] ?? null,
            'total_refunded' => $order['total_refunded'] ?? null,
            'promo_code' => $order['promo_code'] ?? null,
            'is_manually_created' => $order['is_manually_created'] ?? null,
            'notes' => $order['notes'] ?? null,
            'event_id' => $order['event_id'] ?? null,
            'items' => array_key_exists('order_items', $order) ? collect($order['order_items'])
                ->map(static fn (array $item): array => array_filter([
                    'ticket_id' => $item['product_id'] ?? null,
                    'title' => $item['item_name'] ?? null,
                    'quantity' => $item['quantity'] ?? null,
                    'price' => $item['price'] ?? null,
                    'date_id' => $item['event_occurrence_id'] ?? null,
                    'date' => McpPresenter::localDate($item['event_occurrence']['start_date'] ?? null, $timezone),
                ], static fn (mixed $value): bool => $value !== null))
                ->values()
                ->all() : null,
            'attendees' => array_key_exists('attendees', $order) ? collect($order['attendees'])
                ->map(static fn (array $attendee): array => self::attendee($attendee, $timezone))
                ->values()
                ->all() : null,
            'answers' => array_key_exists('question_answers', $order) ? self::answers($order['question_answers']) : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public static function answers(array $answers): array
    {
        return collect($answers)
            ->map(static fn (array $answer): array => array_filter([
                'question_id' => $answer['question_id'] ?? null,
                'question' => $answer['title'] ?? null,
                'answer' => $answer['text_answer'] ?? null,
                'applies_to' => $answer['belongs_to'] ?? null,
                'ticket_title' => $answer['product_title'] ?? null,
                'attendee_id' => $answer['attendee_id'] ?? null,
                'attendee_name' => isset($answer['attendee_id']) ? trim(($answer['first_name'] ?? '').' '.($answer['last_name'] ?? '')) : null,
            ], static fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();
    }
}
