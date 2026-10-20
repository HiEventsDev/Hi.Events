<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Email\Delivery;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDeliveryRecordingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_sent_email_is_recorded_with_its_category_and_message_id(): void
    {
        $sent = Mail::mailer('array')->to('person@example.com')->send(new DeliveryRecordingProbeMail);

        $row = DB::table('email_deliveries')->orderByDesc('id')->first();

        $this->assertSame('delivery_recording_probe', $row->category);
        $this->assertSame($sent->getMessageId(), $row->provider_message_id);
        $this->assertNull($row->delivered_at);
    }

    public function test_faked_mail_records_nothing(): void
    {
        Mail::fake();
        $before = DB::table('email_deliveries')->count();

        Mail::to('person@example.com')->send(new DeliveryRecordingProbeMail);

        $this->assertSame($before, DB::table('email_deliveries')->count());
    }
}

class DeliveryRecordingProbeMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Probe');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Probe</p>');
    }
}
