<?php

namespace Tests\Unit\Services\Domain\OrganizerSubscription;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\OrganizerSubscription\NewOrganizerEventMail;
use HiEvents\Services\Domain\OrganizerSubscription\SubscriptionTokens;
use Tests\TestCase;

class NewOrganizerEventMailTest extends TestCase
{
    public function test_announcement_has_one_click_headers_and_uses_occurrence_dates(): void
    {
        config(['app.key' => 'subscription-test-key', 'app.api_url' => 'https://example.test/api', 'app.frontend_url' => 'https://example.test']);
        $event = (new EventDomainObject)->setId(1)->setOrganizerId(2)->setTitle('New concert')->setTimezone('Europe/Vienna')
            ->setOrganizer((new OrganizerDomainObject)->setId(2)->setName('Organizer'))
            ->setEventOccurrences(collect([
                (new EventOccurrenceDomainObject)->setId(3)->setStartDate(now()->addWeek()->toDateTimeString())->setStatus('ACTIVE'),
                (new EventOccurrenceDomainObject)->setId(4)->setStartDate(now()->addWeeks(2)->toDateTimeString())->setStatus('CANCELLED'),
            ]));
        $mail = new NewOrganizerEventMail($event, 42);
        $this->assertInstanceOf(ContainsEventContent::class, $mail);
        $this->assertSame('List-Unsubscribe=One-Click', $mail->headers()->text['List-Unsubscribe-Post']);
        $this->assertStringContainsString(app(SubscriptionTokens::class)->unsubscribeToken(42), $mail->headers()->text['List-Unsubscribe']);
        $this->assertCount(1, $mail->content()->with['dates']);
        $this->assertStringContainsString('/event/1/', $mail->content()->with['eventUrl']);
        $this->assertSame('New event from Organizer', $mail->envelope()->subject);
    }
}
