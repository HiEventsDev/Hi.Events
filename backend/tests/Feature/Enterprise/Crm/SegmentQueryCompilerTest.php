<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Crm;

use Carbon\CarbonImmutable;
use HiEvents\DomainObjects\Enums\CrmSegmentField;
use HiEvents\DomainObjects\Enums\CrmSegmentOperator;
use HiEvents\Enterprise\Crm\Services\Domain\Segment\SegmentQueryCompiler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SegmentQueryCompilerTest extends TestCase
{
    use BuildsCrmFixtures;
    use DatabaseTransactions;

    private const NOW = '2026-06-15 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCrmOrganizer();
    }

    public function test_no_rules_match_every_contact(): void
    {
        $this->insertContact('ada@example.com');
        $this->insertContact('grace@example.com');

        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->matching([]));
        $this->assertSame(['ada@example.com', 'grace@example.com'], $this->matching([], 'any'));
    }

    public function test_rules_combine_with_all_or_any(): void
    {
        $this->insertContact('both@example.com', ['orders_count' => 3, 'events_count' => 1]);
        $this->insertContact('few-events@example.com', ['orders_count' => 1, 'events_count' => 1]);
        $this->insertContact('neither@example.com', ['orders_count' => 1, 'events_count' => 9]);
        $this->insertContact('many-orders@example.com', ['orders_count' => 3, 'events_count' => 9]);
        $rules = [
            ['field' => 'orders_count', 'operator' => 'gte', 'value' => 2],
            ['field' => 'events_count', 'operator' => 'lte', 'value' => 5],
        ];

        $this->assertSame(['both@example.com'], $this->matching($rules));
        $this->assertSame(['both@example.com', 'few-events@example.com', 'many-orders@example.com'], $this->matching($rules, 'any'));
    }

    public function test_spend_compares_net_spend_in_the_rule_currency(): void
    {
        $this->insertSpend($this->insertContact('refunded@example.com'), 'EUR', 120, 30);
        $this->insertSpend($this->insertContact('big@example.com'), 'EUR', 100, 0);
        $this->insertSpend($this->insertContact('dollars@example.com'), 'USD', 500, 0);
        $this->insertContact('nothing@example.com');

        $this->assertSame(['big@example.com'], $this->matching([$this->spend('gte', 99.5)]));
        $this->assertSame(['refunded@example.com'], $this->matching([$this->spend('eq', 90)]));
        $this->assertSame(
            ['dollars@example.com', 'nothing@example.com', 'refunded@example.com'],
            $this->matching([$this->spend('lte', 90)]),
        );
    }

    public function test_relative_dates_count_back_from_now_and_never_match_an_empty_date(): void
    {
        $now = CarbonImmutable::parse(self::NOW);
        $this->insertContact('recent@example.com', ['last_order_at' => $now->subDays(29)]);
        $this->insertContact('edge@example.com', ['last_order_at' => $now->subDays(30)]);
        $this->insertContact('old@example.com', ['last_order_at' => $now->subDays(30)->subSecond()]);
        $this->insertContact('never@example.com');

        $this->assertSame(['edge@example.com', 'recent@example.com'], $this->matching([['field' => 'last_order_at', 'operator' => 'within_days', 'value' => 30]]));
        $this->assertSame(['old@example.com'], $this->matching([['field' => 'last_order_at', 'operator' => 'not_within_days', 'value' => 30]]));
        $this->assertSame(['never@example.com'], $this->matching([['field' => 'last_order_at', 'operator' => 'is_empty', 'value' => null]]));
    }

    public function test_absolute_dates_leave_out_the_named_day_in_the_organizers_timezone(): void
    {
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'America/New_York']);
        $this->insertContact('day-before@example.com', ['first_seen_at' => '2026-03-01 04:59:59']);
        $this->insertContact('start-of-day@example.com', ['first_seen_at' => '2026-03-01 05:00:00']);
        $this->insertContact('end-of-day@example.com', ['first_seen_at' => '2026-03-02 04:59:59']);
        $this->insertContact('day-after@example.com', ['first_seen_at' => '2026-03-02 05:00:00']);
        $this->insertContact('never@example.com');

        $this->assertSame(['day-before@example.com'], $this->matching([['field' => 'first_seen_at', 'operator' => 'before', 'value' => '2026-03-01']]));
        $this->assertSame(['day-after@example.com'], $this->matching([['field' => 'first_seen_at', 'operator' => 'after', 'value' => '2026-03-01']]));
        $this->assertSame(
            ['day-before@example.com', 'end-of-day@example.com', 'start-of-day@example.com'],
            $this->matching([['field' => 'first_seen_at', 'operator' => 'before', 'value' => '2026-03-02']]),
        );
    }

    public function test_option_fields_match_in_and_not_in_lists_and_treat_values_as_data(): void
    {
        $this->insertContact('subscribed@example.com', ['email_marketing_status' => 'SUBSCRIBED', 'source' => 'ORDER']);
        $this->insertContact('bounced@example.com', ['email_marketing_status' => 'BOUNCED', 'source' => 'IMPORT']);
        $this->insertContact('unsubscribed@example.com', ['email_marketing_status' => 'UNSUBSCRIBED', 'source' => 'ORDER']);

        $this->assertSame(
            ['bounced@example.com', 'subscribed@example.com'],
            $this->matching([['field' => 'email_marketing_status', 'operator' => 'in', 'value' => ['SUBSCRIBED', 'BOUNCED']]]),
        );
        $this->assertSame(
            ['subscribed@example.com', 'unsubscribed@example.com'],
            $this->matching([['field' => 'source', 'operator' => 'not_in', 'value' => ['IMPORT']]]),
        );
        $this->assertSame([], $this->matching([['field' => 'email_marketing_status', 'operator' => 'in', 'value' => ["SUBSCRIBED' OR 'a' = 'a"]]]));
    }

    public function test_every_field_and_operator_runs_against_the_database(): void
    {
        $this->insertContact('ada@example.com');

        foreach (CrmSegmentField::cases() as $field) {
            foreach ($field->kind()->operators() as $operator) {
                try {
                    $this->assertIsArray($this->matching([$this->ruleFor($field, $operator)]));
                } catch (QueryException $exception) {
                    $this->fail("{$field->value} {$operator->value}: {$exception->getMessage()}");
                }
            }
        }
    }

    private function ruleFor(CrmSegmentField $field, CrmSegmentOperator $operator): array
    {
        $value = match (true) {
            $operator === CrmSegmentOperator::BEFORE, $operator === CrmSegmentOperator::AFTER => '2026-01-01',
            $operator === CrmSegmentOperator::IN, $operator === CrmSegmentOperator::NOT_IN => match ($field) {
                CrmSegmentField::EMAIL_MARKETING_STATUS => ['SUBSCRIBED'],
                CrmSegmentField::SOURCE => ['ORDER'],
                default => [987654],
            },
            default => 5,
        };

        return ['field' => $field->value, 'operator' => $operator->value, 'value' => $value, 'currency' => 'USD'];
    }

    private function spend(string $operator, float $value): array
    {
        return ['field' => 'total_spent', 'operator' => $operator, 'value' => $value, 'currency' => 'EUR'];
    }

    private function insertSpend(int $contactId, string $currency, float $gross, float $refunded): void
    {
        DB::table('crm_contact_spend')->insert([
            'contact_id' => $contactId,
            'organizer_id' => $this->organizerId,
            'currency' => $currency,
            'total_gross' => $gross,
            'total_refunded' => $refunded,
        ]);
    }

    /**
     * @return string[]
     */
    private function matching(array $rules, string $match = 'all'): array
    {
        $compiled = app(SegmentQueryCompiler::class)->compile(
            ['match' => $match, 'rules' => $rules],
            $this->organizerId,
            CarbonImmutable::parse(self::NOW),
        );

        return DB::table('crm_contacts')
            ->where('organizer_id', $this->organizerId)
            ->whereRaw($compiled->sql, $compiled->bindings)
            ->orderBy('email')
            ->pluck('email')
            ->all();
    }
}
