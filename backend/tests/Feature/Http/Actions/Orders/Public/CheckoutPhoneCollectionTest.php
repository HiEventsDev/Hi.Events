<?php

namespace Tests\Feature\Http\Actions\Orders\Public;

use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\PhoneCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ManagesFeatureFlags;
use Tests\Concerns\ManagesLicence;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class CheckoutPhoneCollectionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use ManagesFeatureFlags;
    use ManagesLicence;

    private const SESSION = 'phone-checkout-session';

    private int $orderId;

    private int $priceId;

    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent();
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'payment_providers' => json_encode(['OFFLINE']),
            'attendee_details_collection_method' => AttendeeDetailsCollectionMethod::PER_ORDER->value,
            'require_billing_address' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $occurrenceId = $this->insertOccurrence(daysAhead: 10);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);

        $this->orderId = $this->reserve($this->productId, $this->priceId, $occurrenceId, 1);
        DB::table('orders')->where('id', $this->orderId)->update(['session_id' => self::SESSION, 'locale' => 'en']);
    }

    public function test_a_required_mobile_number_must_be_given(): void
    {
        $this->setPhoneCollection(PhoneCollection::REQUIRED);

        $this->complete()->assertStatus(422)->assertJsonValidationErrors('order.phone');
    }

    public function test_a_landline_or_a_number_without_a_country_code_is_rejected(): void
    {
        $this->setPhoneCollection(PhoneCollection::REQUIRED);

        $this->complete('+44 20 7946 0958')->assertStatus(422)->assertJsonValidationErrors('order.phone');
        $this->complete('0412 345 678')->assertStatus(422)->assertJsonValidationErrors('order.phone');
    }

    public function test_the_mobile_number_is_stored_in_international_format(): void
    {
        $this->setPhoneCollection(PhoneCollection::REQUIRED);

        $this->complete('+61 412 345 678')->assertOk();

        $this->assertSame('+61412345678', DB::table('orders')->where('id', $this->orderId)->value('phone'));
    }

    public function test_an_optional_mobile_number_can_be_left_out(): void
    {
        $this->setPhoneCollection(PhoneCollection::OPTIONAL);

        $this->complete()->assertOk();

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('phone'));
    }

    public function test_a_number_sent_when_the_event_does_not_collect_one_is_ignored(): void
    {
        $this->complete('+353 85 123 4567')->assertOk();

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('phone'));
    }

    public function test_the_buyer_can_change_their_number_but_not_remove_a_required_one(): void
    {
        $this->setPhoneCollection(PhoneCollection::REQUIRED);
        $this->complete('+61 412 345 678')->assertOk();
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_attendee_self_edit' => true]);
        $shortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');
        $url = "/public/events/{$this->eventId}/order/{$shortId}";

        $this->patchJson($url, ['phone' => ''])->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->patchJson($url, ['phone' => '+1 415 555 2671'])->assertOk();

        $this->assertSame('+14155552671', DB::table('orders')->where('id', $this->orderId)->value('phone'));
    }

    #[DataProvider('numbersSentToAnEventThatDoesNotAskForOne')]
    public function test_editing_an_order_ignores_a_number_when_the_event_does_not_collect_one(string $phone): void
    {
        $this->complete()->assertOk();
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_attendee_self_edit' => true]);
        $shortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');

        $this->patchJson("/public/events/{$this->eventId}/order/{$shortId}", ['first_name' => 'Rae', 'phone' => $phone])->assertOk();

        $order = DB::table('orders')->where('id', $this->orderId)->first();
        $this->assertSame('Rae', $order->first_name);
        $this->assertNull($order->phone);
        $this->assertNull($order->phone_country);
    }

    public static function numbersSentToAnEventThatDoesNotAskForOne(): array
    {
        return [
            'a valid mobile number' => ['+1 415 555 2671'],
            'something that is not a number' => ['call me'],
        ];
    }

    public function test_a_buyer_who_ticks_the_text_message_box_is_recorded_as_opted_in(): void
    {
        $this->askForTextConsent();

        $this->complete('+61 412 345 678', smsOptedIn: true)->assertOk();

        $this->assertNotNull(DB::table('orders')->where('id', $this->orderId)->value('sms_opted_in_at'));
    }

    public static function numbersInCountriesWeText(): array
    {
        return [
            'United Kingdom' => ['+44 7400 123456'],
            'Ireland' => ['+353 85 123 4567'],
            'United States' => ['+1 202 555 0123'],
            'Australia' => ['+61 412 345 678'],
        ];
    }

    #[DataProvider('numbersInCountriesWeText')]
    public function test_a_text_opt_in_is_recorded_for_a_number_in_a_country_we_text(string $phone): void
    {
        $this->askForTextConsent();

        $this->complete($phone, smsOptedIn: true)->assertOk();

        $this->assertNotNull(DB::table('orders')->where('id', $this->orderId)->value('sms_opted_in_at'));
    }

    public static function numbersInCountriesWeDoNotText(): array
    {
        return [
            'France' => ['+33 6 12 34 56 78', 'FR'],
            'Canada' => ['+1 416 555 0123', 'CA'],
            'Guernsey' => ['+44 7781 123456', 'GG'],
            'Jersey' => ['+44 7797 712345', 'JE'],
            'Isle of Man' => ['+44 7624 123456', 'IM'],
            'Puerto Rico' => ['+1 787 234 5678', 'PR'],
            'Guam' => ['+1 671 555 1234', 'GU'],
        ];
    }

    #[DataProvider('numbersInCountriesWeDoNotText')]
    public function test_a_text_opt_in_for_a_number_we_cannot_text_is_not_recorded_and_the_order_still_completes(string $phone, string $country): void
    {
        $this->askForTextConsent();

        $this->complete($phone, smsOptedIn: true)->assertOk();

        $order = DB::table('orders')->where('id', $this->orderId)->first();
        $this->assertSame($country, $order->phone_country);
        $this->assertNotNull($order->phone);
        $this->assertNull($order->sms_opted_in_at);
    }

    public function test_ticking_the_text_message_box_needs_a_number(): void
    {
        $this->askForTextConsent();

        $this->complete(smsOptedIn: true)->assertStatus(422)->assertJsonValidationErrors('order.phone');
    }

    public function test_a_text_opt_in_is_ignored_when_the_event_does_not_ask_for_one(): void
    {
        $this->setPhoneCollection(PhoneCollection::OPTIONAL);

        $this->complete('+61 412 345 678', smsOptedIn: true)->assertOk();

        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('sms_opted_in_at'));
    }

    public function test_changing_the_number_withdraws_the_text_opt_in_given_for_the_old_one(): void
    {
        $this->askForTextConsent();
        $this->complete('+61 412 345 678', smsOptedIn: true)->assertOk();
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['allow_attendee_self_edit' => true]);
        $shortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');

        $this->patchJson("/public/events/{$this->eventId}/order/{$shortId}", ['phone' => '+61 412 345 678'])->assertOk();
        $this->assertNotNull(DB::table('orders')->where('id', $this->orderId)->value('sms_opted_in_at'));

        $this->patchJson("/public/events/{$this->eventId}/order/{$shortId}", ['phone' => '+1 415 555 2671'])->assertOk();
        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('sms_opted_in_at'));
    }

    public function test_the_public_event_offers_the_text_opt_in_when_the_account_can_send_texts(): void
    {
        $this->askForTextConsent();

        $this->getJson("/public/events/{$this->eventId}")->assertOk()->assertJsonPath('data.settings.show_sms_opt_in', true);
    }

    public static function accountsThatCannotSendTexts(): array
    {
        return [
            'text messages switched off' => ['sms_off'],
            'audience switched off' => ['audience_off'],
            'no audience licence' => ['unlicensed'],
        ];
    }

    #[DataProvider('accountsThatCannotSendTexts')]
    public function test_the_text_opt_in_is_neither_offered_nor_recorded_when_the_account_cannot_send_texts(string $account): void
    {
        $this->askForTextConsent();
        match ($account) {
            'sms_off' => $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, false),
            'audience_off' => $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, false),
            'unlicensed' => $this->useLicenceKey(null),
        };

        $this->getJson("/public/events/{$this->eventId}")->assertOk()->assertJsonPath('data.settings.show_sms_opt_in', false);
        $this->complete('+61 412 345 678', smsOptedIn: true)->assertOk();
        $this->assertNull(DB::table('orders')->where('id', $this->orderId)->value('sms_opted_in_at'));
        $this->assertTrue((bool) DB::table('event_settings')->where('event_id', $this->eventId)->value('show_sms_opt_in'));
    }

    private function complete(?string $phone = null, bool $smsOptedIn = false): TestResponse
    {
        $shortId = DB::table('orders')->where('id', $this->orderId)->value('short_id');

        return $this->putJson("/public/events/{$this->eventId}/order/{$shortId}?session_identifier=".self::SESSION, [
            'order' => array_filter([
                'first_name' => 'Ray',
                'last_name' => 'Buyer',
                'email' => 'buyer@example.test',
                'email_confirmation' => 'buyer@example.test',
                'phone' => $phone,
                'sms_opted_in' => $smsOptedIn,
            ]),
            'products' => [[
                'product_id' => $this->productId,
                'product_price_id' => $this->priceId,
                'first_name' => 'Ray',
                'last_name' => 'Buyer',
                'email' => 'buyer@example.test',
            ]],
        ]);
    }

    private function askForTextConsent(): void
    {
        $this->useCloudLicence();
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM, true);
        $this->setFeatureFlagOverride($this->accountId, FeatureFlag::CRM_SMS, true);
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $this->setPhoneCollection(PhoneCollection::OPTIONAL);
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['show_sms_opt_in' => true]);
    }

    private function setPhoneCollection(PhoneCollection $collection): void
    {
        DB::table('event_settings')->where('event_id', $this->eventId)->update(['phone_collection' => $collection->value]);
    }
}
