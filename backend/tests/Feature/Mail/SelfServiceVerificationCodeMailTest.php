<?php

namespace Tests\Feature\Mail;

use HiEvents\Mail\SelfService\SelfServiceVerificationCodeMail;
use Tests\TestCase;

class SelfServiceVerificationCodeMailTest extends TestCase
{
    public function test_it_shows_the_code_and_how_long_it_lasts_and_nothing_personal(): void
    {
        $mail = new SelfServiceVerificationCodeMail(code: '493027', validForMinutes: 10);

        $mail->assertHasSubject(__('Your verification code'));
        $mail->assertSeeInHtml('493027');
        $mail->assertSeeInHtml('10 minutes');
        $mail->assertDontSeeInHtml('Hello');
    }
}
