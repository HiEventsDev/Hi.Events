<?php

namespace Tests\Feature\Mail;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Mail\BaseMail;
use HiEvents\Mail\Concerns\ContainsEventContent;
use HiEvents\Mail\Concerns\ProvidesEventContent;
use HiEvents\Mail\Organizer\OrderSummaryForOrganizer;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

class UnpublishedEventMailReplacementTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_organizer_content_is_replaced_when_the_policy_withholds_it(): void
    {
        $this->givenPolicyWithholds(true);

        Mail::mailer('array')->to('victim@example.com')->sendNow($this->scamMail());

        $message = $this->onlySentMessage();

        $this->assertSame('An update about your booking on '.config('app.name'), $message->getOriginalMessage()->getSubject());
        $this->assertStringNotContainsString('805', $this->visibleContent($message));
        $this->assertSame('victim@example.com', $message->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_organizer_content_is_sent_when_the_policy_allows_it(): void
    {
        $this->givenPolicyWithholds(false);

        Mail::mailer('array')->to('buyer@example.com')->sendNow($this->scamMail());

        $this->assertSame('Call 805 891 5965', $this->onlySentMessage()->getOriginalMessage()->getSubject());
    }

    public function test_organizer_order_notification_is_replaced_with_an_organizer_notice(): void
    {
        $this->givenPolicyWithholds(true);

        Mail::mailer('array')->to('organizer@example.com')->sendNow(new OrderSummaryForOrganizer(
            (new OrderDomainObject)->setId(77)->setTotalGross(0),
            (new EventDomainObject)->setId(5)->setAccountId(1)->setTitle('Call 805 891 5965')->setCurrency('EUR'),
        ));

        $message = $this->onlySentMessage();

        $this->assertSame('New order on an unpublished event', $message->getOriginalMessage()->getSubject());
        $this->assertStringNotContainsString('805', $this->visibleContent($message));
        $this->assertStringContainsString('/manage/event/5/orders#order-77', $message->toString());
    }

    private function visibleContent(SentMessage $message): string
    {
        $email = $message->getOriginalMessage();

        return implode("\n", [$email->getSubject(), $email->getTextBody(), $email->getHtmlBody()]);
    }

    private function givenPolicyWithholds(bool $withhold): void
    {
        $policy = Mockery::mock(UnpublishedEventMailPolicy::class);
        $policy->shouldReceive('shouldWithholdEventContent')->andReturn($withhold);
        $this->app->instance(UnpublishedEventMailPolicy::class, $policy);
    }

    private function onlySentMessage(): SentMessage
    {
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();

        $this->assertCount(1, $messages);

        return $messages->first();
    }

    private function scamMail(): BaseMail
    {
        return new class((new EventDomainObject)->setId(1)->setAccountId(1)) extends BaseMail implements ContainsEventContent
        {
            use ProvidesEventContent;

            public function __construct(private readonly EventDomainObject $event)
            {
                parent::__construct();
            }

            public function envelope(): Envelope
            {
                return new Envelope(subject: 'Call 805 891 5965');
            }

            public function content(): Content
            {
                return new Content(htmlString: '<p>Call 805 891 5965</p>');
            }
        };
    }
}
