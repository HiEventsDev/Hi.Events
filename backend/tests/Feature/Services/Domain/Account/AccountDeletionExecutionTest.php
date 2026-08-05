<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Account;

use HiEvents\Models\Account;
use HiEvents\Models\AccountUser;
use HiEvents\Models\Attendee;
use HiEvents\Models\Event;
use HiEvents\Models\Order;
use HiEvents\Models\Organizer;
use HiEvents\Models\Product;
use HiEvents\Models\ProductPrice;
use HiEvents\Models\Question;
use HiEvents\Models\QuestionAnswer;
use HiEvents\Models\StripeCustomer;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Account\AccountAnonymizationService;
use HiEvents\Services\Domain\Account\AccountHardDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountDeletionExecutionTest extends TestCase
{
    use DatabaseTransactions;

    private Account $account;

    private Account $otherAccount;

    private User $soleUser;

    private User $sharedUser;

    private Event $event;

    private Order $order;

    private Attendee $attendee;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccountGraph();
    }

    public function test_anonymization_scrubs_pii_and_retains_financial_records(): void
    {
        $manifest = $this->app->make(AccountAnonymizationService::class)
            ->anonymizeAccount($this->account->id);

        $order = $this->order->fresh();
        $this->assertSame('Anonymized', $order->first_name);
        $this->assertStringContainsString('@anonymized.invalid', $order->email);
        $this->assertNull($order->address);
        $this->assertNull($order->session_id);
        $this->assertNotSame('pub-order-1', $order->public_id);
        $this->assertSame('25.00', (string) number_format((float) $order->total_gross, 2));
        $this->assertSame('COMPLETED', $order->status);
        $this->assertNull($order->deleted_at);

        $attendee = $this->attendee->fresh();
        $this->assertSame('Anonymized', $attendee->first_name);
        $this->assertNotSame('pub-att-1', $attendee->public_id);

        $this->assertDatabaseMissing('question_answers', ['question_id' => $this->question->id]);
        $this->assertDatabaseCount('question_answers', 1);

        $soleUser = $this->soleUser->fresh();
        $this->assertSame('Anonymized', $soleUser->first_name);
        $this->assertStringContainsString('@anonymized.invalid', $soleUser->email);
        $this->assertNotNull($soleUser->deleted_at);

        $sharedUser = $this->sharedUser->fresh();
        $this->assertSame('shared@example.com', $sharedUser->email);
        $this->assertNull($sharedUser->deleted_at);

        $this->assertSoftDeleted('account_users', [
            'account_id' => $this->account->id,
            'user_id' => $this->sharedUser->id,
        ]);
        $this->assertDatabaseHas('account_users', [
            'account_id' => $this->otherAccount->id,
            'user_id' => $this->sharedUser->id,
            'deleted_at' => null,
        ]);

        $account = $this->account->fresh();
        $this->assertSame('Anonymized', $account->name);
        $this->assertNotNull($account->deleted_at);

        $this->assertDatabaseHas('stripe_customers', [
            'stripe_account_id' => 'acct_deltest123',
            'name' => 'Anonymized',
        ]);

        $this->assertNotEmpty($manifest);
        $this->assertContains('orders', array_column($manifest, 'entity'));
    }

    public function test_hard_deletion_removes_account_graph_and_preserves_shared_users(): void
    {
        $this->app->make(AccountHardDeletionService::class)
            ->deleteAccount($this->account->id);

        $this->assertDatabaseMissing('accounts', ['id' => $this->account->id]);
        $this->assertDatabaseMissing('events', ['id' => $this->event->id]);
        $this->assertDatabaseMissing('orders', ['id' => $this->order->id]);
        $this->assertDatabaseMissing('attendees', ['id' => $this->attendee->id]);
        $this->assertDatabaseMissing('questions', ['event_id' => $this->event->id]);
        $this->assertDatabaseMissing('users', ['id' => $this->soleUser->id]);
        $this->assertDatabaseMissing('stripe_customers', ['stripe_account_id' => 'acct_deltest123']);

        $this->assertDatabaseHas('users', ['id' => $this->sharedUser->id]);
        $this->assertDatabaseHas('accounts', ['id' => $this->otherAccount->id]);
        $this->assertDatabaseCount('question_answers', 1);
    }

    private function seedAccountGraph(): void
    {
        $this->account = Account::forceCreate([
            'name' => 'Doomed Account',
            'email' => 'owner@example.com',
            'timezone' => 'UTC',
            'currency_code' => 'USD',
            'short_id' => 'acc_doomed',
            'stripe_account_id' => 'acct_deltest123',
        ]);

        $this->otherAccount = Account::forceCreate([
            'name' => 'Surviving Account',
            'email' => 'other@example.com',
            'timezone' => 'UTC',
            'currency_code' => 'USD',
            'short_id' => 'acc_survivor',
        ]);

        $this->soleUser = User::forceCreate([
            'email' => 'sole@example.com',
            'first_name' => 'Sole',
            'last_name' => 'User',
            'password' => 'hash',
            'timezone' => 'UTC',
        ]);

        $this->sharedUser = User::forceCreate([
            'email' => 'shared@example.com',
            'first_name' => 'Shared',
            'last_name' => 'User',
            'password' => 'hash',
            'timezone' => 'UTC',
        ]);

        AccountUser::forceCreate([
            'account_id' => $this->account->id,
            'user_id' => $this->soleUser->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'is_account_owner' => true,
        ]);
        AccountUser::forceCreate([
            'account_id' => $this->account->id,
            'user_id' => $this->sharedUser->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);
        AccountUser::forceCreate([
            'account_id' => $this->otherAccount->id,
            'user_id' => $this->sharedUser->id,
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
        ]);

        $organizer = Organizer::forceCreate([
            'name' => 'Doomed Organizer',
            'account_id' => $this->account->id,
            'email' => 'organizer@example.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'LIVE',
        ]);

        $this->event = $this->seedEvent('Past Event', $this->account->id, $organizer->id, $this->soleUser->id, 'evt_doomed');

        $product = Product::forceCreate([
            'event_id' => $this->event->id,
            'title' => 'Ticket',
            'type' => 'PAID',
            'product_type' => 'TICKET',
            'order' => 1,
        ]);

        $productPrice = ProductPrice::forceCreate([
            'product_id' => $product->id,
            'price' => 25,
            'order' => 1,
        ]);

        $this->order = Order::forceCreate([
            'event_id' => $this->event->id,
            'short_id' => 'ord_doomed',
            'public_id' => 'pub-order-1',
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@example.com',
            'address' => ['line1' => '1 Main St'],
            'session_id' => 'sess-123',
            'total_before_additions' => 25,
            'total_gross' => 25,
            'currency' => 'USD',
            'status' => 'COMPLETED',
        ]);

        $this->attendee = Attendee::forceCreate([
            'order_id' => $this->order->id,
            'event_id' => $this->event->id,
            'product_id' => $product->id,
            'product_price_id' => $productPrice->id,
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'status' => 'ACTIVE',
            'public_id' => 'pub-att-1',
            'short_id' => 'att_doomed',
        ]);

        $this->question = Question::forceCreate([
            'event_id' => $this->event->id,
            'title' => 'Dietary requirements',
            'type' => 'SINGLE_LINE_TEXT',
            'belongs_to' => 'ORDER',
            'required' => false,
            'is_hidden' => false,
        ]);

        QuestionAnswer::forceCreate([
            'question_id' => $this->question->id,
            'order_id' => $this->order->id,
            'answer' => ['text' => 'Nut allergy'],
        ]);

        $otherOrganizer = Organizer::forceCreate([
            'name' => 'Surviving Organizer',
            'account_id' => $this->otherAccount->id,
            'email' => 'surviving@example.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'status' => 'LIVE',
        ]);

        $otherEvent = $this->seedEvent('Surviving Event', $this->otherAccount->id, $otherOrganizer->id, $this->sharedUser->id, 'evt_survivor');

        $otherQuestion = Question::forceCreate([
            'event_id' => $otherEvent->id,
            'title' => 'Other question',
            'type' => 'SINGLE_LINE_TEXT',
            'belongs_to' => 'ORDER',
            'required' => false,
            'is_hidden' => false,
        ]);

        $otherOrder = Order::forceCreate([
            'event_id' => $otherEvent->id,
            'short_id' => 'ord_survivor',
            'public_id' => 'pub-order-2',
            'first_name' => 'Sam',
            'last_name' => 'Survivor',
            'email' => 'sam@example.com',
            'total_before_additions' => 10,
            'total_gross' => 10,
            'currency' => 'USD',
            'status' => 'COMPLETED',
        ]);

        QuestionAnswer::forceCreate([
            'question_id' => $otherQuestion->id,
            'order_id' => $otherOrder->id,
            'answer' => ['text' => 'Should survive'],
        ]);

        StripeCustomer::forceCreate([
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'stripe_customer_id' => 'cus_deltest1',
            'stripe_account_id' => 'acct_deltest123',
        ]);

        DB::table('question_answers')->whereNotIn('question_id', [$this->question->id, $otherQuestion->id])->delete();
    }

    private function seedEvent(string $title, int $accountId, int $organizerId, int $userId, string $shortId): Event
    {
        $eventId = DB::table('events')->insertGetId([
            'title' => $title,
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'user_id' => $userId,
            'short_id' => $shortId,
            'status' => 'DRAFT',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Event::findOrFail($eventId);
    }
}
