<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait CreatesBadgeFixtures
{
    use ActsAsAccountUsers;

    protected function createBadgeEvent(int $accountId, string $currency = 'EUR', bool $badgesEnabled = true): int
    {
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        DB::table('events')->where('id', $eventId)->update(['currency' => $currency]);
        DB::table('event_settings')->insert([
            'event_id' => $eventId,
            'enabled_features' => json_encode($badgesEnabled ? ['BADGES'] : []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $eventId;
    }

    protected function createTicketProduct(int $eventId, string $title = 'General Admission'): int
    {
        return DB::table('products')->insertGetId([
            'title' => $title,
            'event_id' => $eventId,
            'order' => 1,
            'product_type' => 'TICKET',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createQuestion(int $eventId, string $title, string $belongsTo = 'PRODUCT', string $type = 'SINGLE_LINE_TEXT'): int
    {
        return DB::table('questions')->insertGetId([
            'event_id' => $eventId,
            'title' => $title,
            'type' => $type,
            'belongs_to' => $belongsTo,
            'required' => false,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{id: int, public_id: string, order_id: int}
     */
    protected function createBadgeAttendee(
        int $eventId,
        int $productId,
        string $firstName,
        string $lastName,
        string $status = 'ACTIVE',
        string $orderStatus = 'COMPLETED',
        ?int $orderId = null,
    ): array {
        $priceId = DB::table('product_prices')->where('product_id', $productId)->value('id')
            ?? DB::table('product_prices')->insertGetId([
                'product_id' => $productId,
                'price' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $orderId ??= DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $eventId,
            'currency' => 'EUR',
            'status' => $orderStatus,
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => 'buyer@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicId = 'A-'.strtoupper(substr(uniqid(), -7)).random_int(10, 99);
        $id = DB::table('attendees')->insertGetId([
            'short_id' => 'att_'.uniqid(),
            'email' => strtolower($firstName).'@example.test',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'event_id' => $eventId,
            'public_id' => $publicId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'public_id' => $publicId, 'order_id' => $orderId];
    }

    protected function answerQuestion(int $questionId, int $orderId, ?int $attendeeId, string|array $answer, ?int $productId = null): void
    {
        DB::table('question_answers')->insert([
            'question_id' => $questionId,
            'order_id' => $orderId,
            'attendee_id' => $attendeeId,
            'product_id' => $productId,
            'answer' => json_encode($answer),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function badgeDesignPayload(array $overrides = []): array
    {
        return array_replace([
            'template' => 'CLASSIC',
            'size' => 'A6_PORTRAIT',
            'custom_width_mm' => null,
            'custom_height_mm' => null,
            'sheet' => 'A4',
            'accent_color' => '#1d4ed8',
            'background_color' => null,
            'text_color' => null,
            'font_family' => 'Inter',
            'text_align' => null,
            'uppercase_names' => false,
            'lines' => [
                ['source' => 'FIRST_NAME', 'size' => 'XL'],
                ['source' => 'LAST_NAME', 'size' => 'L'],
            ],
            'show_logo' => true,
            'show_qr' => true,
            'show_ribbon' => true,
            'show_seat' => true,
            'show_event_name' => true,
            'show_sponsors' => true,
            'footer_text' => null,
            'ribbons' => [],
            'back_mode' => 'NONE',
            'back_text' => null,
        ], $overrides);
    }
}
