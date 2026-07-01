<?php

namespace Tests\Unit\Resources\Event;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Resources\Event\EventSettingsResourcePublic;
use Illuminate\Http\Request;
use Tests\TestCase;

class EventSettingsResourcePublicTest extends TestCase
{
    public function test_public_resource_exposes_allow_copy_details_when_enabled(): void
    {
        $settings = (new EventSettingDomainObject())
            ->setAllowCopyDetailsToAllAttendees(true);

        $resource = (new EventSettingsResourcePublic($settings))->toArray(Request::create('/'));

        // Load-bearing: the checkout can only hide the control if this flag is on the
        // public payload, so it must always be present (outside the post-checkout block).
        $this->assertArrayHasKey('allow_copy_details_to_all_attendees', $resource);
        $this->assertTrue($resource['allow_copy_details_to_all_attendees']);
    }

    public function test_public_resource_exposes_allow_copy_details_when_disabled(): void
    {
        $settings = (new EventSettingDomainObject())
            ->setAllowCopyDetailsToAllAttendees(false);

        $resource = (new EventSettingsResourcePublic($settings))->toArray(Request::create('/'));

        $this->assertFalse($resource['allow_copy_details_to_all_attendees']);
    }

    public function test_offline_payment_instructions_render_order_tokens(): void
    {
        $resource = $this->makeResource(
            '<p>Use {{ order.number }} for {{ event.title }}</p>',
            orderFirstName: 'Jane',
        );

        $data = $resource->toArray(Request::create('/'));

        $this->assertSame(
            '<p>Use ORD-12345 for Summer Session</p>',
            $data['offline_payment_instructions'],
        );
    }

    public function test_rendered_offline_payment_instructions_are_purified(): void
    {
        $resource = $this->makeResource(
            '<p>Reference {{ order.first_name }}</p>',
            orderFirstName: '<script>alert("xss")</script>Jane',
        );

        $data = $resource->toArray(Request::create('/'));

        $this->assertStringNotContainsString('<script>', $data['offline_payment_instructions']);
        $this->assertStringContainsString('Jane', $data['offline_payment_instructions']);
    }

    private function makeResource(
        string $offlinePaymentInstructions,
        string $orderFirstName,
    ): EventSettingsResourcePublic {
        $organizer = (new OrganizerDomainObject())
            ->setId(1)
            ->setName('Example Organizer')
            ->setEmail('organizer@example.com');

        $event = (new EventDomainObject())
            ->setId(10)
            ->setTitle('Summer Session')
            ->setDescription('An evening event')
            ->setStartDate('2026-08-15 18:00:00')
            ->setCurrency('GBP')
            ->setTimezone('UTC')
            ->setOrganizer($organizer);

        $settings = (new EventSettingDomainObject())
            ->setId(20)
            ->setEventId(10)
            ->setPaymentProviders([PaymentProviders::OFFLINE->value])
            ->setSupportEmail('support@example.com')
            ->setOfflinePaymentInstructions($offlinePaymentInstructions);

        $order = (new OrderDomainObject())
            ->setId(30)
            ->setEventId(10)
            ->setShortId('order-short-id')
            ->setPublicId('ORD-12345')
            ->setFirstName($orderFirstName)
            ->setLastName('Buyer')
            ->setEmail('buyer@example.com')
            ->setTotalGross(125.50)
            ->setCurrency('GBP')
            ->setCreatedAt('2026-08-01 12:00:00')
            ->setStatus(OrderStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentStatus(OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name)
            ->setPaymentProvider(PaymentProviders::OFFLINE->value);

        return new EventSettingsResourcePublic(
            resource: $settings,
            eventContext: $event,
            orderContext: $order,
        );
    }
}
