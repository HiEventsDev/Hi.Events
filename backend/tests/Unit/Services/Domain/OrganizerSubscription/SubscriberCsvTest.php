<?php

namespace Tests\Unit\Services\Domain\OrganizerSubscription;

use HiEvents\Services\Domain\OrganizerSubscription\SubscriberCsv;
use Tests\TestCase;

class SubscriberCsvTest extends TestCase
{
    public function test_export_escapes_formulas_and_preserves_normal_addresses(): void
    {
        $csv = new SubscriberCsv;
        foreach (['=1+1', '+SUM(A1)', '-1', '@formula', "\t=1"] as $value) {
            $this->assertSame("'".$value, $csv->safeCell($value));
        }
        $this->assertSame('buyer@example.com', $csv->safeCell('buyer@example.com'));
        $stream = fopen('php://temp', 'w+');
        $csv->write($stream, [(object) [
            'email' => '=formula@example.test', 'source' => 'public', 'subscribed_at' => '2026-11-16',
            'confirmed_at' => null, 'unsubscribed_at' => null,
        ]]);
        rewind($stream);
        fgetcsv($stream, escape: '');
        $row = fgetcsv($stream, escape: '');
        fclose($stream);
        $this->assertSame("'=formula@example.test", $row[0]);
        $this->assertSame('Pending confirmation', $row[1]);
    }
}
