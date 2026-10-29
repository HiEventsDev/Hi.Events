<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\Services\Domain\Mail\EmailAccentColorService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailAccentColorServiceTest extends TestCase
{
    private EmailAccentColorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EmailAccentColorService;
    }

    public function test_a_dark_accent_gets_white_button_text_and_is_used_for_links(): void
    {
        $accent = $this->service->fromAccent('#1B36F5');

        $this->assertSame('#1b36f5', $accent->buttonColor);
        $this->assertSame('#ffffff', $accent->buttonTextColor);
        $this->assertSame('#1b36f5', $accent->linkColor);
    }

    public function test_a_mid_tone_accent_styles_the_button_but_not_body_links(): void
    {
        $accent = $this->service->fromAccent('#ff5a36');

        $this->assertSame('#ff5a36', $accent->buttonColor);
        $this->assertSame('#111111', $accent->buttonTextColor);
        $this->assertNull($accent->linkColor);
    }

    #[DataProvider('nearWhiteAccents')]
    public function test_accents_too_close_to_white_are_ignored(string $accent): void
    {
        $this->assertNull($this->service->fromAccent($accent));
    }

    public static function nearWhiteAccents(): array
    {
        return [
            'white' => ['#ffffff'],
            'lavender wash' => ['#f5f3ff'],
            'neon yellow' => ['#D0FF14'],
        ];
    }

    public function test_short_hex_is_expanded(): void
    {
        $this->assertSame('#ff6600', $this->service->fromAccent('#f60')->buttonColor);
        $this->assertSame('#ff6600', $this->service->fromAccent('#f60a')->buttonColor);
    }

    public function test_the_alpha_channel_is_dropped(): void
    {
        $this->assertSame('#7c3aed', $this->service->fromAccent('#7c3aedff')->buttonColor);
    }

    #[DataProvider('invalidAccents')]
    public function test_invalid_accents_are_ignored(?string $accent): void
    {
        $this->assertNull($this->service->fromAccent($accent));
    }

    public static function invalidAccents(): array
    {
        return [
            'missing' => [null],
            'five digits' => ['#12345'],
            'css injection' => ['#000000; background: url(x)'],
            'trailing newline' => ["#000000\n"],
            'named colour' => ['red'],
        ];
    }
}
