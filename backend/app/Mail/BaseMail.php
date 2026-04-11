<?php

namespace HiEvents\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

abstract class BaseMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        $this->afterCommit();
    }

    abstract public function envelope(): Envelope;

    abstract public function content(): Content;

    public function headers(): Headers
    {
        $configSet = config('services.ses.configuration_set');

        if (!$configSet) {
            return new Headers();
        }

        return new Headers(
            text: ['X-SES-CONFIGURATION-SET' => $configSet],
        );
    }
}
