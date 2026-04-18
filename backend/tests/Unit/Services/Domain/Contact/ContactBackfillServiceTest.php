<?php

namespace Tests\Unit\Services\Domain\Contact;

use HiEvents\Services\Domain\Contact\ContactBackfillService;
use Tests\TestCase;

class ContactBackfillServiceTest extends TestCase
{
    public function test_resolve_contact_email_uses_attendee_email_when_attendee_id_present(): void
    {
        $row = [
            'attendee_id' => 42,
            'attendee_email' => 'attendee@example.com',
            'buyer_email' => 'buyer@example.com',
        ];

        $this->assertSame('attendee@example.com', ContactBackfillService::resolveContactEmail($row));
    }

    public function test_resolve_contact_email_falls_back_to_buyer_email_for_order_level_answer(): void
    {
        $row = [
            'attendee_id' => null,
            'attendee_email' => null,
            'buyer_email' => 'buyer@example.com',
        ];

        $this->assertSame('buyer@example.com', ContactBackfillService::resolveContactEmail($row));
    }

    public function test_resolve_contact_email_returns_null_when_attendee_id_present_but_attendee_email_missing(): void
    {
        $row = [
            'attendee_id' => 42,
            'attendee_email' => null,
            'buyer_email' => 'buyer@example.com',
        ];

        $this->assertNull(ContactBackfillService::resolveContactEmail($row));
    }

    public function test_normalize_attributes_accepts_array(): void
    {
        $this->assertSame(['k' => 'v'], ContactBackfillService::normalizeAttributes(['k' => 'v']));
    }

    public function test_normalize_attributes_decodes_json_string(): void
    {
        $this->assertSame(['k' => 'v'], ContactBackfillService::normalizeAttributes('{"k":"v"}'));
    }

    public function test_normalize_attributes_returns_empty_for_other_inputs(): void
    {
        $this->assertSame([], ContactBackfillService::normalizeAttributes(null));
        $this->assertSame([], ContactBackfillService::normalizeAttributes(42));
    }

    public function test_decode_answer_decodes_json_array_string(): void
    {
        $this->assertSame(['yes', 'no'], ContactBackfillService::decodeAnswer('["yes","no"]'));
    }

    public function test_decode_answer_leaves_scalar_string_intact(): void
    {
        $this->assertSame('plain value', ContactBackfillService::decodeAnswer('plain value'));
    }

    public function test_decode_answer_leaves_array_intact(): void
    {
        $this->assertSame(['a', 'b'], ContactBackfillService::decodeAnswer(['a', 'b']));
    }

    public function test_decode_answer_unwraps_json_encoded_scalar_string(): void
    {
        $this->assertSame('Cobb', ContactBackfillService::decodeAnswer('"Cobb"'));
    }

    public function test_decode_answer_unwraps_json_encoded_object(): void
    {
        $this->assertSame(['k' => 'v'], ContactBackfillService::decodeAnswer('{"k":"v"}'));
    }
}
