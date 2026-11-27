<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Identity\ContactEmailNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactEmailNormalizerTest extends TestCase
{
    public static function validEmails(): array
    {
        return [
            'already normal' => ['ada@example.com', 'ada@example.com'],
            'mixed case' => ['Ada.Lovelace@Example.COM', 'ada.lovelace@example.com'],
            'surrounding whitespace' => ["  ada@example.com\n", 'ada@example.com'],
            'tabs and carriage returns' => ["\tAda@Example.com\r\n", 'ada@example.com'],
            'plus addressing is kept' => ['Ada+Tickets@example.com', 'ada+tickets@example.com'],
            'multibyte is lowercased' => ['ÅSA@EXAMPLE.SE', 'åsa@example.se'],
        ];
    }

    #[DataProvider('validEmails')]
    public function test_an_email_is_trimmed_and_lowercased(string $email, string $expected): void
    {
        $this->assertSame($expected, (new ContactEmailNormalizer)->normalize($email));
    }

    public static function invalidEmails(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace only' => ['   '],
            'no at sign' => ['ada.example.com'],
            'two at signs' => ['ada@home@example.com'],
            'no local part' => ['@example.com'],
            'no domain' => ['ada@'],
            'inner space' => ['ada lovelace@example.com'],
            'inner tab' => ["ada\t@example.com"],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_an_unusable_email_normalizes_to_null(?string $email): void
    {
        $this->assertNull((new ContactEmailNormalizer)->normalize($email));
    }

    public function test_the_length_limit_is_inclusive(): void
    {
        $normalizer = new ContactEmailNormalizer;
        $domain = '@example.com';
        $atLimit = str_repeat('a', ContactEmailNormalizer::MAX_LENGTH - strlen($domain)).$domain;

        $this->assertSame($atLimit, $normalizer->normalize($atLimit));
        $this->assertNull($normalizer->normalize('a'.$atLimit));
    }
}
